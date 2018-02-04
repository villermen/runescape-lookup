<?php

namespace App\Controller;

use App\Entity\DailyRecord;
use App\Entity\PersonalRecord;
use App\Entity\TrackedActivityFeedItem;
use App\Entity\TrackedHighScore;
use App\Entity\TrackedPlayer;
use App\Service\TimeKeeper;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Villermen\RuneScape\Exception\FetchFailedException;
use Villermen\RuneScape\Exception\RuneScapeException;
use Villermen\RuneScape\Player;
use Villermen\RuneScape\PlayerDataFetcher;

/**
 * @Route("/", name="lookup_")
 */
class LookupController extends Controller
{
    /**
     * @param Request $request
     * @return Response
     *
     * @Route("", name="index")
     */
    public function indexAction(Request $request)
    {
        // Forward to other actions based on parameters
        $name1 = $request->query->get("player1");
        $name2 = $request->query->get("player2");
        $oldSchool = $request->query->has("oldschool");

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

        return $this->forward(self::class . "::overviewAction", [], $query);
    }

    /**
     * @param EntityManagerInterface $entityManager
     * @param TimeKeeper $timeKeeper
     * @return Response
     */
    public function overviewAction(EntityManagerInterface $entityManager, TimeKeeper $timeKeeper)
    {
        $dailyRecords = $entityManager->getRepository(DailyRecord::class)->findByDate($timeKeeper->getUpdateTime(-1), false);
        $dailyOldSchoolRecords = $entityManager->getRepository(DailyRecord::class)->findByDate($timeKeeper->getUpdateTime(-1), true);

        $trackedPlayers = $entityManager->getRepository(TrackedPlayer::class)->findAll();

        return $this->render("lookup/overview.html.twig", [
            "dailyRecords" => $dailyRecords,
            "dailyOldSchoolRecords" => $dailyOldSchoolRecords,
            "trackedPlayers" => $trackedPlayers,
            "updateTime" => $timeKeeper->getUpdateTime(1)->format("G:i"),
            "timezone" => date_default_timezone_get(),
            "timeTillUpdate" => (new DateTime())->diff($timeKeeper->getUpdateTime(1))->format("%h:%I")
        ]);
    }

    /**
     * @param string $name
     * @param bool $oldSchool
     * @param EntityManagerInterface $entityManager
     * @param TimeKeeper $timeKeeper
     * @param PlayerDataFetcher $dataFetcher
     * @return Response
     */
    public function playerAction(string $name, bool $oldSchool, EntityManagerInterface $entityManager,
        TimeKeeper $timeKeeper, PlayerDataFetcher $dataFetcher)
    {
        // TODO: Track and retrack logic

        $error = "";
        $stats = false;
        $trainedToday = false;
        $trainedYesterday = false;
        $trainedWeek = false;
        $records = [];
        $activityFeed = false;

        // Try to obtain a tracked player from the database
        $player = $entityManager->getRepository(TrackedPlayer::class)->findByName($name);
        if (!$player) {
            try {
                $player = new Player($name, $dataFetcher);
            } catch (RuneScapeException $exception) {
                $error = "Invalid player name requested.";
            }
        }

        if ($player) {
            // Fetch live stats
            try {
                $stats = $oldSchool ? $player->getOldSchoolSkillHighScore() : $player->getSkillHighScore();
            } catch (FetchFailedException $exception) {
                $error = "Could not fetch player stats.";
            }

            if ($stats) {
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
            "oldSchool" => $oldSchool
        ]);
    }

    /**
     * @param string $name1
     * @param string $name2
     * @param bool $oldSchool
     * @return Response
     */
    public function compareAction(string $name1, string $name2, bool $oldSchool)
    {
        // TODO: Implement

        return $this->render("lookup/compare.html.twig", [
            "name1" => $name1,
            "name2" => $name2,
            "oldSchool" => $oldSchool
        ]);
    }
}
