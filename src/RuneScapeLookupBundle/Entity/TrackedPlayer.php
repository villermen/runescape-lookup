<?php

namespace RuneScapeLookupBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Villermen\RuneScape\Player;

/**
 * @ORM\Entity(repositoryClass="RuneScapeLookupBundle\Repository\TrackedPlayerRepository")
 * @ORM\Table(name="tracked_player")
 */
class TrackedPlayer extends Player
{
    /**
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id()
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @inheritdoc
     *
     * @ORM\Column(name="name", type="string", length=12)
     */
    protected $name;

    /** TODO: Map */
    protected $highscores;

    public function __construct($name)
    {
        parent::__construct($name);

        $this->highscores = new ArrayCollection();
    }
}