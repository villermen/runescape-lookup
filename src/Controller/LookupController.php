<?php

namespace App\Controller;

use App\Entity\DailyRecord;
use App\Entity\PersonalRecord;
use App\Entity\TrackedActivityFeedItem;
use App\Entity\TrackedHighScore;
use App\Entity\TrackedPlayer;
use App\Service\TimeKeeper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Villermen\RuneScape\Activity;
use Villermen\RuneScape\Exception\FetchFailedException;
use Villermen\RuneScape\Exception\RuneScapeException;
use Villermen\RuneScape\Player;
use Villermen\RuneScape\PlayerDataFetcher;

/**
 * @Route("/", name="lookup_")
 */
class LookupController extends AbstractController
{
    /**
     * @Route("", name="index")
     */
    public function indexAction(Request $request): Response
    {
        // Forward to other actions based on parameters
        $name1 = $request->query->get("player1");
        $name2 = $request->query->get("player2");
        $oldSchool = (bool)$request->query->get("oldschool");

        // Remove standard parameters but pass on the others when forwarding
        $query = array_filter($request->query->all(), function($parameter) {
            return !in_array($parameter, ["player1", "player2", "oldSchool"]);
        }, ARRAY_FILTER_USE_KEY);

        if ($name1) {
            if ($name2) {
                return $this->forward(self::class . "::compareAction", [
                    "name1" => $name1,
                    "name2" => $name2,
                    "oldSchool" => $oldSchool
                ], $query);
            }

            return $this->forward(self::class . "::playerAction", [
                "name" => $name1,
                "oldSchool" => $oldSchool
            ], $query);
        }

        return $this->forward(self::class . "::overviewAction", [
            "name2" => $name2
        ], $query);
    }

    public function overviewAction(
        ?string $name2,
        EntityManagerInterface $entityManager,
        TimeKeeper $timeKeeper
    ): Response {
        // Fetch yesterday's records
        $dailyRecords = $entityManager->getRepository(DailyRecord::class)->findByDate($timeKeeper->getUpdateTime(-1), false);
        $dailyOldSchoolRecords = $entityManager->getRepository(DailyRecord::class)->findByDate($timeKeeper->getUpdateTime(-1), true);

        $trackedPlayers = $entityManager->getRepository(TrackedPlayer::class)->findAll();

        return $this->render("lookup/overview.html.twig", [
            "dailyRecords" => $dailyRecords,
            "dailyOldSchoolRecords" => $dailyOldSchoolRecords,
            "trackedPlayers" => $trackedPlayers,
            "updateTime" => $timeKeeper->getUpdateTime(1)->format("G:i"),
            "timeTillUpdate" => (new \DateTime())->diff($timeKeeper->getUpdateTime(1))->format("%h:%I"),
            "name2" => $name2
        ]);
    }

    public function playerAction(
        string $name,
        bool $oldSchool,
        EntityManagerInterface $entityManager,
        TimeKeeper $timeKeeper,
        PlayerDataFetcher $dataFetcher,
        Request $request
    ): Response {
        $error = "";
        $stats = false;
        $trainedToday = false;
        $trainedYesterday = false;
        $trainedWeek = false;
        $records = [];
        $activityFeed = false;
        $runeScore = null;

        // Try to obtain a tracked player from the database
        $player = $entityManager->getRepository(TrackedPlayer::class)->findByName($name);
        if (!$player) {
            if (Player::validateName($name)) {
                $player = new Player($name, $dataFetcher);
            } else {
                $error = "Invalid player name requested.";
            }
        }

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
                $error = "Could not fetch player stats.";
            }

            if ($stats) {
                // Track or retrack player
                if ($request->query->get("track")) {
                    if (!($player instanceof TrackedPlayer)) {
                        $player = new TrackedPlayer($player->getName());
                        $entityManager->persist($player);
                        $entityManager->flush();

                        return $this->redirectToRoute("lookup_index", [
                            "player1" => $player->getName(),
                            "oldschool" => $oldSchool
                        ]);
                    } elseif (!$player->isActive()) {
                        $player->setActive(true);
                        $entityManager->flush();

                        return $this->redirectToRoute("lookup_index", [
                            "player1" => $player->getName(),
                            "oldschool" => $oldSchool
                        ]);
                    } else {
                        $error = "Player is already being tracked.";
                    }
                }

                if ($player instanceof TrackedPlayer) {
                    // Fetch and compare tracked stats
                    $trackedHighScoreRepository = $entityManager->getRepository(TrackedHighScore::class);

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
                    $records = $entityManager->getRepository(PersonalRecord::class)->findHighestRecords($player, $oldSchool);

                    if (!$oldSchool) {
                        // Get tracked and live activity feed
                        $activityFeed = $entityManager->getRepository(TrackedActivityFeedItem::class)->findByPlayer($player, true);
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

        return $this->render("lookup/player.html.twig", [
            "error" => $error,
            "player" => $player,
            "stats" => $stats,
            "tracked" => $player instanceof TrackedPlayer,
            "trainedToday" => $trainedToday,
            "trainedYesterday" => $trainedYesterday,
            "trainedWeek" => $trainedWeek,
            "name1" => $name,
            "records" => $records,
            "activityFeed" => $activityFeed,
            "oldSchool" => $oldSchool,
            "runeScore" => $runeScore,
        ]);
    }

    public function compareAction(
        string $name1,
        string $name2,
        bool $oldSchool,
        EntityManagerInterface $entityManager,
        PlayerDataFetcher $dataFetcher,
        TimeKeeper $timeKeeper
    ): Response {
        $error = "";
        $stats1 = false;
        $stats2 = false;
        $trained1 = false;
        $trained2 = false;
        $player2 = false;
        $comparison = false;
        $runeScore1 = null;
        $runeScore2 = null;

        // Get player objects
        $player1 = $entityManager->getRepository(TrackedPlayer::class)->findByName($name1);
        if (!$player1) {
            if (Player::validateName($name1)) {
                $player1 = new Player($name1, $dataFetcher);
            } else {
                $error = "Player 1's name is invalid.";
            }
        }

        if ($player1) {
            $player2 = $entityManager->getRepository(TrackedPlayer::class)->findByName($name2);
            if (!$player2) {
                if (Player::validateName($name2)) {
                    $player2 = new Player($name2, $dataFetcher);
                } else {
                    $error = "Player 2's name is invalid.";
                }
            }
        }

        if ($player1 && $player2) {
            $trackedHighScoreRepository = $entityManager->getRepository(TrackedHighScore::class);

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
                $error = "Could not fetch stats for player 1.";
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
                    $error = "Could not fetch stats for player 2.";
                }
            }

            if ($stats1 && $stats2) {
                $comparison = $stats1->compareTo($stats2);
            }
        }

        return $this->render("lookup/compare.html.twig", [
            "error" => $error,
            "player1" => $player1,
            "player2" => $player2,
            "stats1" => $stats1,
            "stats2" => $stats2,
            "trained1" => $trained1,
            "trained2" => $trained2,
            "comparison" => $comparison,
            "name1" => $name1,
            "name2" => $name2,
            "oldSchool" => $oldSchool,
            "tracked1" => $player1 instanceof TrackedPlayer,
            "tracked2" => $player2 instanceof TrackedPlayer,
            "runeScore1" => $runeScore1,
            "runeScore2" => $runeScore2,
        ]);
    }
}
