<?php

namespace App\Controller;

use App\Entity\DailyRecord;
use App\Entity\TrackedPlayer;
use App\Service\TimeKeeper;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

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

        if ($name1 && $name2) {
            return $this->redirectToRoute("app_lookup_compare", [
                "name1" => $name1,
                "name2" => $name2
            ]);
        } elseif ($name1) {
            return $this->redirectToRoute("app_lookup_player", [
                "name" => $name1
            ]);
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
     * @return Response
     *
     * @Route("/{name}", name="lookup_player")
     */
    public function playerAction(string $name)
    {
        return new Response("lookup " . htmlspecialchars($name));
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
