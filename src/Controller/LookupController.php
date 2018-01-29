<?php

namespace App\Controller;

use App\Entity\DailyRecord;
use App\Entity\TrackedHighScore;
use App\Entity\TrackedPlayer;
use App\Service\TimeKeeper;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Villermen\RuneScape\HighScore\HighScoreSkill;
use Villermen\RuneScape\HighScore\HighScoreSkillComparison;
use Villermen\RuneScape\Player;
use Villermen\RuneScape\RuneScapeException;

/**
 * @Route("/", name="app_")
 */
class LookupController extends Controller
{
    /**
     * @param EntityManagerInterface $entityManager
     * @param TimeKeeper $timeKeeper
     * @param Request $request
     * @return Response
     *
     * @Route("", name="lookup_index")
     */
    public function indexAction(EntityManagerInterface $entityManager, TimeKeeper $timeKeeper, Request $request)
    {
        // Redirect to other actions from index for backwards compatibility
        $name1 = $request->query->get("player1");
        $name2 = $request->query->get("player2");

        if ($name1) {
            // Remove name query parameters but pass on the others
            $query = array_filter($request->query->all(), function($parameter) {
                return !in_array($parameter, ["player1", "player2"]);
            }, ARRAY_FILTER_USE_KEY);

            if ($name2) {
                return $this->redirectToRoute("app_lookup_compare", array_merge([
                    "name1" => $name1,
                    "name2" => $name2
                ], $query));
            }

            return $this->redirectToRoute("app_lookup_player", array_merge([
                "name" => $name1
            ], $query));
        }

        $dailyRecords = $entityManager->getRepository(DailyRecord::class)->findBy(
            ["date" => new DateTime()],
            ["xpGain" => "desc"]
        );

        $trackedPlayers = $entityManager->getRepository(TrackedPlayer::class)->findBy(
            [],
            ["name" => "asc"]
        );

        return $this->render("lookup/index.html.twig", [
            "dailyRecords" => $dailyRecords,
            "trackedPlayers" => $trackedPlayers,
            "updateTime" => $timeKeeper->getUpdateTime(1)->format("G:i"),
            "timezone" => date_default_timezone_get(),
            "timeTillUpdate" => (new DateTime())->diff($timeKeeper->getUpdateTime(1))->format("%h:%I")
        ]);
    }

    /**
     * @param string $name
     * @param EntityManagerInterface $entityManager
     * @param Request $request
     * @param TimeKeeper $timeKeeper
     * @return Response
     *
     * @Route("/{name}", name="lookup_player")
     */
    public function playerAction(string $name, EntityManagerInterface $entityManager, Request $request,
        TimeKeeper $timeKeeper)
    {
        $error = "";
        $stats = false;
        $trainedToday = false;
        $trainedYesterday = false;
        $trainedWeek = false;

        $player = $entityManager->getRepository(TrackedPlayer::class)->findOneBy(["name" => $name]);

        // Try to obtain a tracked player from the database
        if (!$player) {
            try {
                $player = new Player($name);
            } catch (RuneScapeException $exception) {
                $error = "Invalid player name requested.";
            }
        }

        if ($player) {
            try {
                // Fetch live stats
                $stats = $player->getHighScore($request->query->has("oldschool"));
            } catch (RuneScapeException $exception) {
                $error = "Could not fetch player stats.";
            }
        }

        if ($player && $player instanceof TrackedPlayer) {
            // Fetch and compare tracked stats
            $trackedHighScoreRepository = $entityManager->getRepository(TrackedHighScore::class);

            $highScoreToday = $trackedHighScoreRepository->findOneBy(["date" => $timeKeeper->getUpdateTime(0)]);
            if ($highScoreToday) {
                // TODO: Full HighScore comparison instead of per skill?
            }
        }

        return $this->render("lookup/player.html.twig", [
            "error" => $error,
            "player" => $player,
            "stats" => $stats,
            "tracked" => $player instanceof TrackedPlayer,
            "trainedToday" => $trainedToday,
            "trainedYesterday" => $trainedYesterday,
            "trainedWeek" => $trainedWeek
        ]);
    }

    /**
     * @param string $name1
     * @param string $name2
     * @return Response
     *
     * @Route("/{name1}/{name2}", name="lookup_compare")
     */
    public function compareAction(string $name1, string $name2)
    {
        return new Response("compare " . htmlspecialchars($name1) . " " . htmlspecialchars($name2));
    }
}
