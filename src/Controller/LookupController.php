<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use App\Entity\TrackedPlayer;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Villermen\RuneScape\RuneScapeException;

class LookupController extends Controller
{
    /**
     * @param EntityManagerInterface $entityManager
     * @return Response
     * @throws RuneScapeException
     *
     * @Route("/")
     */
    public function indexAction(EntityManagerInterface $entityManager)
    {
        // TODO: Daily high score
        // TODO: Personal daily high score

        $player = new TrackedPlayer("Villermen");
        $trackedHighScore = $player->addTrackedHighScore();

        dump($player);
        dump($trackedHighScore);

        return new Response("index");
    }

    /**
     * @param string $name1
     * @return Response
     *
     * @Route("/{name1}")
     */
    public function lookupAction(string $name1)
    {
        return new Response("lookup " . htmlspecialchars($name1));
    }

    /**
     * @param string $name1
     * @param string $name2
     * @return Response
     *
     * @Route("/{name1}/{name2}")
     */
    public function compareAction(string $name1, string $name2)
    {
        return new Response("compare " . htmlspecialchars($name1) . " " . htmlspecialchars($name2));
    }
}
