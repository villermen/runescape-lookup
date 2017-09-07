<?php

namespace RuneScapeLookupBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class LookupController extends Controller
{
    /**
     * @return Response
     *
     * @Route("/")
     */
    public function indexAction()
    {
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
