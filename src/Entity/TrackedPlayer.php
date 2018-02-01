<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Villermen\RuneScape\ActivityFeed\ActivityFeed;
use Villermen\RuneScape\ActivityFeed\ActivityFeedItem;
use Villermen\RuneScape\Player;
use Villermen\RuneScape\RuneScapeException;

/**
 * @ORM\Entity(repositoryClass="App\Repository\TrackedPlayerRepository")
 * @ORM\Table(name="player")
 * @ORM\HasLifecycleCallbacks()
 */
class TrackedPlayer extends Player
{
    /**
     * @ORM\Column(type="integer")
     * @ORM\Id()
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @inheritdoc
     *
     * @ORM\Column(type="string", length=12, unique=true)
     */
    protected $name;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean")
     */
    protected $active = true;

    /**
     * @param string $name
     * @throws RuneScapeException
     */
    public function __construct(string $name)
    {
        parent::__construct($name);
    }

    /**
     * Loads the player's high score data and adds an entry of it to the tracked high scores of this player.
     *
     * @param bool $oldSchool
     * @param int $timeOut
     * @return TrackedHighScore
     * @throws RuneScapeException
     */
    public function addTrackedHighScore($oldSchool = false, $timeOut = 5): TrackedHighScore
    {
        $data = $this->getHighScoreData($oldSchool, $timeOut);
        $trackedHighScore = new TrackedHighScore($data, $this, $oldSchool);

        return $trackedHighScore;
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * @param bool $active
     *
     * @return TrackedPlayer
     */
    public function setActive(bool $active): TrackedPlayer
    {
        $this->active = $active;

        return $this;
    }

    /**
     * @ORM\PostLoad()
     * @throws RuneScapeException
     */
    public function postLoad()
    {
        parent::__construct($this->getName());
    }
}
