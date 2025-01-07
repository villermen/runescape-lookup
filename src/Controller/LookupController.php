<?php

namespace App\Controller;

use App\Entity\DailyRecord;
use App\Entity\PersonalRecord;
use App\Entity\TrackedActivityFeedItem;
use App\Entity\TrackedHighScore;
use App\Entity\TrackedPlayer;
use App\Repository\DailyRecordRepository;
use App\Repository\TrackedPlayerRepository;
use App\Service\TimeKeeper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Villermen\RuneScape\Exception\FetchFailedException;
use Villermen\RuneScape\Player;

/**
 * @phpstan-type LookupParameters array{player1: Player|null, player2: Player|null, oldschool: bool}
 */
class LookupController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TrackedPlayerRepository $trackedPlayerRepository,
        private readonly DailyRecordRepository $dailyRecordRepository,
        private readonly TimeKeeper $timeKeeper,
    ) {
    }

    #[Route('/', 'lookup')]
    public function lookupAction(Request $request): Response
    {
        $player1 = $request->query->getString('player1') ?: null;
        $player1 = $player1 ? new Player($player1) : null;
        $player2 = $request->query->getString('player2') ?: null;
        $player2 = $player2 ? new Player($player2) : null;

        /** @var LookupParameters $lookupParameters */
        $lookupParameters = [
            'player1' => $player1,
            'player2' => $player2,
            'oldschool' => $request->query->getBoolean('oldschool'),
        ];

        if ($lookupParameters['player1'] && $lookupParameters['player2']) {
            return $this->forward(implode('::', [self::class, 'compareAction']), [
                'lookupParameters' => $lookupParameters,
            ]);
        }

        if ($lookupParameters['player1']) {
            return $this->forward(implode('::', [self::class, 'playerAction']), [
                'lookupParameters' => $lookupParameters,
            ]);
        }

        return $this->forward(implode('::', [self::class, 'overviewAction']), [
            'lookupParameters' => $lookupParameters,
        ]);
    }

    /**
     * @param LookupParameters $lookupParameters
     */
    public function overviewAction(
        array $lookupParameters,
    ): Response {
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
            'lookupParameters' => $lookupParameters,
        ]);
    }

    /**
     * @param LookupParameters $lookupParameters
     */
    public function playerAction(
        array $lookupParameters,
        Request $request
    ): Response {
        $error = '';
        $stats = false;
        $trainedToday = false;
        $trainedYesterday = false;
        $trainedWeek = false;
        $records = [];
        $activityFeed = false;
        $runeScore = null;

        // Try to obtain a tracked player from the database
        $player = $this->entityManager->getRepository(TrackedPlayer::class)->findByName($lookupParameters['player1']->getName());

        if ($player) {
            // Fetch live stats
            try {
                if (!$oldSchool) {
                    $stats = $player->getSkillHighScore();

                    // It is possible for RuneMetrics to return stats without the user being listed on index_lite, so
                    // this can fail individually.
                    try {
                        $runeScore = $player->getActivityHighScore()->getActivity(Activity::ACTIVITY_RUNESCORE);
                    } catch (FetchFailedException $exception) {
                    }
                } else {
                    $stats = $player->getOldSchoolSkillHighScore();
                }

                $player->fixNameIfCached();
            } catch (FetchFailedException $exception) {
                $error = 'Could not fetch player stats.';
            }

            if ($stats) {
                // Track or retrack player
                if ($request->query->get('track')) {
                    if (!($player instanceof TrackedPlayer)) {
                        $player = new TrackedPlayer($player->getName());
                        $this->entityManager->persist($player);
                        $this->entityManager->flush();

                        return $this->redirectToRoute('lookup', [
                            'player1' => $player->getName(),
                            'oldschool' => $oldSchool
                        ]);
                    } elseif (!$player->isActive()) {
                        $player->setActive(true);
                        $this->entityManager->flush();

                        return $this->redirectToRoute('lookup', [
                            'player1' => $player->getName(),
                            'oldschool' => $oldSchool
                        ]);
                    } else {
                        $error = 'Player is already being tracked.';
                    }
                }

                if ($player instanceof TrackedPlayer) {
                    // Fetch and compare tracked stats
                    $trackedHighScoreRepository = $this->entityManager->getRepository(TrackedHighScore::class);

                    $highScoreToday = $trackedHighScoreRepository->findByDate($timeKeeper->getUpdateTime(0), $player, $oldSchool);
                    if ($highScoreToday) {
                        $trainedToday = $stats->compareTo($highScoreToday);

                        $highScoreYesterday = $trackedHighScoreRepository->findByDate($timeKeeper->getUpdateTime(-1), $player, $oldSchool);
                        if ($highScoreYesterday) {
                            $trainedYesterday = $highScoreToday->compareTo($highScoreYesterday);
                        }

                        $highScoreWeek = $trackedHighScoreRepository->findByDate($timeKeeper->getUpdateTime(-7), $player, $oldSchool);
                        if ($highScoreWeek) {
                            $trainedWeek = $highScoreToday->compareTo($highScoreWeek);
                        }
                    }

                    // Get records
                    $records = $this->entityManager->getRepository(PersonalRecord::class)->findHighestRecords($player, $oldSchool);

                    if (!$oldSchool) {
                        // Get tracked and live activity feed
                        $activityFeed = $this->entityManager->getRepository(TrackedActivityFeedItem::class)->findByPlayer($player, true);
                    }
                } else {
                    // Only fetch live activity feed
                    try {
                        if (!$oldSchool) {
                            $activityFeed = $player->getActivityFeed();
                        }
                    } catch (FetchFailedException $exception) {
                    }
                }
            }
        }

        return $this->render('lookup/player.html.twig', [
            'error' => $error,
            'player' => $player,
            'stats' => $stats,
            'tracked' => $player instanceof TrackedPlayer,
            'trainedToday' => $trainedToday,
            'trainedYesterday' => $trainedYesterday,
            'trainedWeek' => $trainedWeek,
            'name1' => $name,
            'records' => $records,
            'activityFeed' => $activityFeed,
            'oldSchool' => $oldSchool,
            'runeScore' => $runeScore,
        ]);
    }

    /**
     * @param LookupParameters $lookupParameters
     */
    public function compareAction(
        array $lookupParameters,
    ): Response {
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
}
