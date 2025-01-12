<?php

namespace App\Controller;

use App\Entity\TrackedHighScore;
use App\Entity\TrackedPlayer;
use App\Repository\DailyRecordRepository;
use App\Repository\PersonalRecordRepository;
use App\Repository\TrackedHighScoreRepository;
use App\Repository\TrackedPlayerRepository;
use App\Service\TimeKeeper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Villermen\RuneScape\ActivityFeed\ActivityFeed;
use Villermen\RuneScape\Exception\FetchFailedException;
use Villermen\RuneScape\HighScore\HighScore;
use Villermen\RuneScape\HighScore\OsrsActivity;
use Villermen\RuneScape\HighScore\OsrsHighScore;
use Villermen\RuneScape\HighScore\OsrsSkill;
use Villermen\RuneScape\Player;
use Villermen\RuneScape\PlayerData\RuneMetricsData;
use Villermen\RuneScape\Service\PlayerDataFetcher;

class LookupController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TrackedPlayerRepository $trackedPlayerRepository,
        private readonly DailyRecordRepository $dailyRecordRepository,
        private readonly TimeKeeper $timeKeeper,
        private readonly PlayerDataFetcher $playerDataFetcher,
        private readonly TrackedHighScoreRepository $trackedHighScoreRepository,
        private readonly PersonalRecordRepository $personalRecordRepository,
    ) {
    }

    #[Route('/', 'overview')]
    public function overviewAction(Request $request): Response
    {
        // Poor man's form handling.
        $errors = [];
        $player1 = $request->query->getString('player1') ?: null;
        if ($player1 && !Player::validateName($player1)) {
            $errors[] = 'Name of player 1 is invalid!';
        }
        $player2 = $request->query->getString('player2') ?: null;
        if ($player2 && !Player::validateName($player2)) {
            $errors[] = 'Name of player 2 is invalid!';
        }
        $oldschool = $request->query->getBoolean('oldschool');
        $game = $oldschool ? 'osrs' : 'rs3';

        if (!$errors && $player1 && $player2) {
            return $this->redirectToRoute('compare', [
                'game' => $game,
                'name1' => $player1,
                'name2' => $player2,
            ]);
        }

        if (!$errors && $player1) {
            return $this->redirectToRoute('player', [
                'game' => $game,
                'name' => $player1,
            ]);
        }

        // TODO: Group lookup would be nice too.

        // Fetch yesterday's records
        $dailyRecords = $this->dailyRecordRepository->findByDate($this->timeKeeper->getUpdateTime(-1), oldSchool: false);
        $dailyOldSchoolRecords = $this->dailyRecordRepository->findByDate($this->timeKeeper->getUpdateTime(-1), oldSchool: true);
        $trackedPlayers = $this->trackedPlayerRepository->findAll();
        $updateTime = $this->timeKeeper->getUpdateTime(1);
        $timeTillUpdate = (new \DateTime())->diff($updateTime);

        return $this->render('lookup/overview.html.twig', [
            'dailyRecords' => $dailyRecords,
            'dailyOldSchoolRecords' => $dailyOldSchoolRecords,
            'trackedPlayers' => $trackedPlayers,
            'updateTime' => $updateTime->format('G:i'),
            'timeTillUpdate' => $timeTillUpdate->format('%h:%I'),
            'form' => [
                'errors' => $errors,
                'player1' => $player1,
                'player2' => $player2,
                'oldschool' => $oldschool,
            ],
        ]);
    }

    #[Route('/{game<rs3|osrs>}/player/{name}', 'player')]
    public function playerAction(Request $request, string $game, string $name): Response
    {
        if (!Player::validateName($name)) {
            return $this->redirectToRoute('overview', [
                'game' => $game,
                'player1' => $name,
            ]);
        }

        $player = new Player($name);
        $oldSchool = $game === 'osrs';

        /** @var HighScore|null $highScore */
        $highScore = null;
        /** @var RuneMetricsData|null $runeMetrics */
        $runeMetrics = null;
        /** @var ActivityFeed|null $activityFeed */
        $activityFeed = null;

        try {
            $highScore = $this->playerDataFetcher->fetchIndexLite($player, oldSchool: $oldSchool);
        } catch (FetchFailedException) {
        }

        if (!$oldSchool) {
            try {
                $runeMetrics = $this->playerDataFetcher->fetchRuneMetrics($player);
                // Use RuneMetrics as fallback (index_lite includes activities).
                $highScore = $highScore ?? $runeMetrics->highScore;
                $activityFeed = $runeMetrics->activityFeed;
            } catch (FetchFailedException) {
            }
        }

        if (!$highScore) {
            $this->addFlash('error', sprintf('Could not fetch %s highscores for player "%s".', strtoupper($game), $name));
            return $this->redirectToRoute('overview', [
                'game' => $game,
                'player1' => $name,
            ]);
        }

        $trackedPlayer = $this->trackedPlayerRepository->findByName($name);

        // Track or retrack player
        if (!$trackedPlayer?->isActive() && $request->query->getBoolean('track')) {
            if ($trackedPlayer) {
                $trackedPlayer->setActive(true);
            } else {
                $trackedPlayer = new TrackedPlayer($runeMetrics->displayName ?? $player->getName());
                $this->entityManager->persist($trackedPlayer);
            }

            $this->entityManager->flush();

            return $this->redirectToRoute('player', [
                'game' => $game,
                'name' => $name,
            ]);
        }

        if ($trackedPlayer) {
            $highScoreToday = $this->trackedHighScoreRepository->findByDate($this->timeKeeper->getUpdateTime(), $trackedPlayer, $oldSchool)?->getHighScore();
            $highScoreYesterday = $this->trackedHighScoreRepository->findByDate($this->timeKeeper->getUpdateTime(-1), $trackedPlayer, $oldSchool)?->getHighScore();
            $highScoreWeek = $this->trackedHighScoreRepository->findByDate($this->timeKeeper->getUpdateTime(-7), $trackedPlayer, $oldSchool)?->getHighScore();

            $trainedToday = $highScoreToday ? $highScore->compareTo($highScoreToday) : null;
            $trainedYesterday = $highScoreYesterday ? $highScoreToday?->compareTo($highScoreYesterday) : null;
            $trainedWeek = $highScoreWeek ? $highScoreToday?->compareTo($highScoreWeek) : null;
            // TODO: Could return
            $records = $this->personalRecordRepository->findHighestRecords($player, $oldSchool);

            // TODO: Get tracked and live activity feed
            // $activityFeed = $this->entityManager->getRepository(TrackedActivityFeedItem::class)->findByPlayer($player, true);
        } else {
            $trainedToday = null;
            $trainedYesterday = null;
            $trainedWeek = null;
            $records = [];
        }

        return $this->render('lookup/player.html.twig', [
            'player' => $player,
            'highScore' => $highScore,
            'trainedToday' => $trainedToday,
            'trainedYesterday' => $trainedYesterday,
            'trainedWeek' => $trainedWeek,
            'activityFeed' => $activityFeed,
            'records' => $records,
            'oldSchool' => $oldSchool,
            'tracked' => $trackedPlayer?->isActive() ?? false,
        ]);
    }

    #[Route('/{game<rs3|osrs>}/compare/{name}', 'compare')]
    public function compareAction(): Response
    {
        $error = '';
        $stats1 = false;
        $stats2 = false;
        $trained1 = false;
        $trained2 = false;
        $player2 = false;
        $comparison = false;
        $runeScore1 = null;
        $runeScore2 = null;

        // Get player objects
        $player1 = $this->entityManager->getRepository(TrackedPlayer::class)->findByName($name1);
        if (!$player1) {
            if (Player::validateName($name1)) {
                $player1 = new Player($name1, $dataFetcher);
            } else {
                $error = 'Player 1\'s name is invalid.';
            }
        }

        if ($player1) {
            $player2 = $this->entityManager->getRepository(TrackedPlayer::class)->findByName($name2);
            if (!$player2) {
                if (Player::validateName($name2)) {
                    $player2 = new Player($name2, $dataFetcher);
                } else {
                    $error = 'Player 2\'s name is invalid.';
                }
            }
        }

        if ($player1 && $player2) {
            $trackedHighScoreRepository = $this->entityManager->getRepository(TrackedHighScore::class);

            // Fetch stats
            try {
                $stats1 = $oldSchool ? $player1->getOldSchoolSkillHighScore() : $player1->getSkillHighScore();
                $player1->fixNameIfCached();

                if (!$oldSchool) {
                    try {
                        $runeScore1 = $player1->getActivityHighScore()->getActivity(Activity::ACTIVITY_RUNESCORE);
                    } catch (FetchFailedException $exception) {
                    }
                }

                // Calculate trained1
                if ($player1 instanceof TrackedPlayer) {
                    $highScoreToday1 = $trackedHighScoreRepository->findByDate($timeKeeper->getUpdateTime(0), $player1, $oldSchool);

                    if ($highScoreToday1) {
                        $highScoreYesterday1 = $trackedHighScoreRepository->findByDate($timeKeeper->getUpdateTime(-1), $player1, $oldSchool);

                        if ($highScoreYesterday1) {
                            $trained1 = $highScoreToday1->compareTo($highScoreYesterday1);
                        }
                    }
                }
            } catch (FetchFailedException $exception) {
                $error = 'Could not fetch stats for player 1.';
            }

            if ($stats1) {
                try {
                    $stats2 = $oldSchool ? $player2->getOldSchoolSkillHighScore() : $player2->getSkillHighScore();
                    $player2->fixNameIfCached();

                    if (!$oldSchool) {
                        try {
                            $runeScore2 = $player2->getActivityHighScore()->getActivity(Activity::ACTIVITY_RUNESCORE);
                        } catch (FetchFailedException $exception) {
                        }
                    }

                    // Calculate trained2
                    if ($player2 instanceof TrackedPlayer) {
                        $highScoreToday2 = $trackedHighScoreRepository->findByDate($timeKeeper->getUpdateTime(0), $player2, $oldSchool);

                        if ($highScoreToday2) {
                            $highScoreYesterday2 = $trackedHighScoreRepository->findByDate($timeKeeper->getUpdateTime(-1), $player2, $oldSchool);

                            if ($highScoreYesterday2) {
                                $trained2 = $highScoreToday2->compareTo($highScoreYesterday2);
                            }
                        }
                    }
                } catch (FetchFailedException $exception) {
                    $error = 'Could not fetch stats for player 2.';
                }
            }

            if ($stats1 && $stats2) {
                $comparison = $stats1->compareTo($stats2);
            }
        }

        return $this->render('lookup/compare.html.twig', [
            'error' => $error,
            'player1' => $player1,
            'player2' => $player2,
            'stats1' => $stats1,
            'stats2' => $stats2,
            'trained1' => $trained1,
            'trained2' => $trained2,
            'comparison' => $comparison,
            'name1' => $name1,
            'name2' => $name2,
            'oldSchool' => $oldSchool,
            'tracked1' => $player1 instanceof TrackedPlayer,
            'tracked2' => $player2 instanceof TrackedPlayer,
            'runeScore1' => $runeScore1,
            'runeScore2' => $runeScore2,
        ]);
    }

    // TODO: Check both regular and hardcore?
    #[Route('{game<rs3|osrs>}/group/{name}', 'group')]
    public function groupAction(string $name): Response
    {
        try {
            $group = $this->playerDataFetcher->fetchGroupIronman($name);
        } catch (FetchFailedException) {
            $this->addFlash('error', 'Ironman group "%s" does not exist.');
            return $this->redirectToRoute('overview');
        }

        // TODO: Order by total level > xp
        // TODO: Abstract the service into LookupService with HighScore|TrackedHighScore?
        // TODO: Some static typing would be nice...
        // [player, highscore, trackedHighscore?, trained?]
        /** @var array<array{name: string, highScore: OsrsHighScore}> $players */
        $players = [];
        foreach ($group->players as $player) {
            $players[] = [
                'name' => $player->getName(),
                'highScore' => $this->playerDataFetcher->fetchIndexLite($player, oldSchool: true),
            ];
        }

        usort($players, function (array $player1, array $player2): int {
            $total1 = $player1['highScore']->getSkill(OsrsSkill::TOTAL);
            $total2 = $player2['highScore']->getSkill(OsrsSkill::TOTAL);
            return $total1->level !== $total2->level
                ? $total2->level <=> $total1->level
                : $total2->xp <=> $total1->xp;
        });

        $skills = OsrsSkill::cases();
        $highestXps = [];
        foreach ($skills as $skill) {
            foreach ($players as ['highScore' => $highScore]) {
                $highestXps[$skill->getId()] = max($highestXps[$skill->getId()] ?? 0, $highScore->getSkill($skill)->xp);
            }
        }

        // Only include activities for which one of the group members is ranked.
        $activities = array_filter(OsrsActivity::cases(), function (OsrsActivity $activity) use ($players): bool {
            foreach ($players as ['highScore' => $highScore]) {
                $highScoreActivity = $highScore->getActivity($activity);
                if ($highScoreActivity->score) {
                    return true;
                }
            }

            return false;
        });
        $highestScores = [];
        foreach ($activities as $activity) {
            foreach ($players as ['highScore' => $highScore]) {
                $highestScores[$activity->getId()] = max($highestScores[$activity->getId()] ?? 0, $highScore->getActivity($activity)->score);
            }
        }

        return $this->render('lookup/group.html.twig', [
            'group' => $group,
            'players' => $players,
            'skills' =>  $skills,
            'highestXps' => $highestXps,
            'activities' => $activities,
            'highestScores' => $highestScores,
        ]);
    }
}
