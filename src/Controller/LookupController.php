<?php

namespace App\Controller;

use App\Entity\TrackedHighScore;
use App\Entity\TrackedPlayer;
use App\Model\LookupResult;
use App\Repository\DailyRecordRepository;
use App\Repository\TrackedPlayerRepository;
use App\Service\LookupService;
use App\Service\TimeKeeper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Villermen\RuneScape\Exception\FetchFailedException;
use Villermen\RuneScape\HighScore\OsrsSkill;
use Villermen\RuneScape\Player;
use Villermen\RuneScape\Service\PlayerDataFetcher;

class LookupController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TrackedPlayerRepository $trackedPlayerRepository,
        private readonly DailyRecordRepository $dailyRecordRepository,
        private readonly TimeKeeper $timeKeeper,
        private readonly PlayerDataFetcher $playerDataFetcher,
        private readonly LookupService $lookupService,
    ) {
    }

    #[Route('/', 'lookup')]
    public function indexAction(Request $request): Response
    {
        // Backward-compatible form handling.
        $name1 = $request->query->getString('player1') ?: null;
        $name2 = $request->query->getString('player2') ?: null;
        $oldSchool = $request->query->getBoolean('oldschool');
        $group = $request->query->getString('group') ?: null;
        $game = $oldSchool ? 'osrs' : 'rs3';

        if ($group) {
            return $this->redirectToRoute('lookup_group', [
                'game' => $game,
                'name' => $group,
            ]);
        }

        if ($name1 && $name2) {
            return $this->redirectToRoute('lookup_compare', [
                'game' => $game,
                'name1' => $name1,
                'name2' => $name2,
            ]);
        }

        if ($name1) {
            return $this->redirectToRoute('lookup_player', [
                'game' => $game,
                'name' => $name1,
            ]);
        }

        // Fetch yesterday's records
        $dailyRecords = $this->dailyRecordRepository->findRecords(oldSchool: false);
        $dailyOldSchoolRecords = $this->dailyRecordRepository->findRecords(oldSchool: true);
        $updateTime = $this->timeKeeper->getUpdateTime(1);
        $timeTillUpdate = (new \DateTime())->diff($updateTime);

        return $this->render('lookup/overview.html.twig', [
            'dailyRecords' => $dailyRecords,
            'dailyOldSchoolRecords' => $dailyOldSchoolRecords,
            'updateTime' => $updateTime->format('G:i'),
            'timeTillUpdate' => $timeTillUpdate->format('%h:%I'),
            'formValues' => [
                'oldSchool' => $oldSchool,
                'name1' => $name1,
                'name2' => $name2,
            ],
        ]);
    }

    #[Route('/{game<rs3|osrs>}/player/{name}', 'lookup_player')]
    public function playerAction(Request $request, string $game, string $name): Response
    {
        if (!Player::validateName($name)) {
            throw new BadRequestException(sprintf('Invalid name "%s".', $name));
        }

        $player = new Player($name);
        $oldSchool = $game === 'osrs';

        $lookupResult = $this->lookupService->lookUpPlayer($player, $oldSchool);
        if (!$lookupResult) {
            throw new NotFoundHttpException(sprintf('%s player "%s" could not be found.', strtoupper($game), $name));
        }

        if (!$lookupResult->isTracked() && $request->query->getBoolean('track')) {
            $trackedPlayer = $this->lookupService->trackPlayer($player);

            // Immediately update tracked high scores to start tracking progress immediately.
            $this->lookupService->updateTrackedHighScores($trackedPlayer);

            return $this->redirectToRoute('lookup_player', [
                'game' => $game,
                'name' => $name,
            ]);
        }

        return $this->render('lookup/player.html.twig', [
            'lookup' => $lookupResult,
        ]);
    }

    #[Route('/{game<rs3|osrs>}/compare/{name1}/{name2}', 'lookup_compare')]
    public function compareAction(string $game, string $name1, string $name2): Response
    {
        if (!Player::validateName($name1)) {
            throw new BadRequestException(sprintf('Invalid name "%s".', $name1));
        }

        if (!Player::validateName($name2)) {
            throw new BadRequestException(sprintf('Invalid name "%s".', $name2));
        }

        $player1 = new Player($name1);
        $player2 = new Player($name2);

        $trackedPlayer1 = $this->trackedPlayerRepository->findByName($player1->getName());
        $trackedPlayer2 = $this->trackedPlayerRepository->findByName($player2->getName());

        $error = '';
        $stats1 = false;
        $stats2 = false;
        $trained1 = false;
        $trained2 = false;
        $player2 = false;
        $comparison = false;
        $runeScore1 = null;
        $runeScore2 = null;

        if ($trackedPlayer1 && $trackedPlayer2) {
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

    #[Route('{game<rs3|osrs>}/group/{name}', 'lookup_group')]
    public function groupAction(string $game, string $name): Response
    {
        $oldSchool = $game === 'osrs';

        try {
            $group = $this->playerDataFetcher->fetchGroupIronman($name, $oldSchool);
        } catch (FetchFailedException $exception) {
            throw new NotFoundHttpException(sprintf('%s ironman group "%s" could not be found.', strtoupper($game), $name), $exception);
        }

        /** @var LookupResult[] $lookups */
        $lookups = [];
        foreach ($group->players as $player) {
            $lookup = $this->lookupService->lookUpPlayer($player, $oldSchool);
            if (!$lookup) {
                throw new NotFoundHttpException(sprintf('%s player "%s" could not be found.', strtoupper($game), $player->getName()));
            }

            $lookups[] = $lookup;
        }

        // Order by total level > XP.
        usort($lookups, function (LookupResult $lookup1, LookupResult $lookup2): int {
            $total1 = $lookup1->highScore->getSkill(OsrsSkill::TOTAL);
            $total2 = $lookup2->highScore->getSkill(OsrsSkill::TOTAL);
            return $total1->level !== $total2->level
                ? $total2->level <=> $total1->level
                : $total2->xp <=> $total1->xp;
        });

        $skills = [];
        foreach (reset($lookups)->highScore->getSkills() as $highScoreSkill) {
            $skill = $highScoreSkill->skill;
            $highestXp = 0;
            foreach ($lookups as $lookup) {
                $highestXp = max($highestXp, $lookup->highScore->getSkill($skill)->xp ?? 0);
            }

            $skills[] = [
                'skill' => $skill,
                'highestXp' => $highestXp,
            ];
        }

        $activities = [];
        foreach (reset($lookups)->highScore->getActivities() as $highScoreActivity) {
            $activity = $highScoreActivity->activity;
            $highestScore = 0;
            foreach ($lookups as $lookup) {
                $highestScore = max($highestScore, $lookup->highScore->getActivity($activity)->score ?? 0);
            }

            // Only includes activities for which one of the group members is ranked.
            if ($highestScore > 0) {
                $activities[] = [
                    'activity' => $activity,
                    'highestScore' => $highestScore,
                ];
            }
        }


        return $this->render('lookup/group.html.twig', [
            'group' => $group,
            'lookups' => $lookups,
            'skills' => $skills,
            'activities' => $activities,
        ]);
    }
}
