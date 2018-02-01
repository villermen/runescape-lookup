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
     * @var Collection|TrackedHighScore[]
     *
     * @ORM\OneToMany(targetEntity="App\Entity\TrackedHighScore", mappedBy="player", fetch="LAZY")
     * @ORM\OrderBy({"date"="DESC"})
     */
    protected $trackedHighScores;

    /**
     * @var Collection|TrackedActivityFeedItem[]
     *
     * @ORM\OneToMany(targetEntity="App\Entity\TrackedActivityFeedItem", mappedBy="player", fetch="LAZY")
     * @ORM\OrderBy({"time"="DESC"})
     */
    protected $trackedActivityFeedItems;

    /**
     * @param string $name
     * @throws RuneScapeException
     */
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->trackedHighScores = new ArrayCollection();
        $this->trackedActivityFeedItems = new ArrayCollection();
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
     * Returns all tracked activity feed items from latest to earliest.
     *
     * @param bool $includeLive Fetch live activity feed and prepend.
     * @param int $liveTimeOut
     * @return ActivityFeedItem[]
     */
    public function getActivityFeedItems($includeLive = false, $liveTimeOut = 5)
    {
        $liveItems = [];

        if ($includeLive) {
            try {
                $liveActivityFeed = $this->getActivityFeed($liveTimeOut);

                if ($this->trackedActivityFeedItems->count() > 0) {
                    $liveItems = $liveActivityFeed->getNewerItems($this->trackedActivityFeedItems->first());
                } else {
                    $liveItems = $liveActivityFeed->getItems();
                }
            } catch (RuneScapeException $exception) {
            }
        }

        return array_merge($liveItems, $this->trackedActivityFeedItems->toArray());
    }

    /**
     * @ORM\PostLoad()
     */
    public function postLoad()
    {
        parent::__construct($this->getName());
    }

    /**
     * @return TrackedHighScore[]|Collection
     */
    public function getTrackedHighScores()
    {
        return $this->trackedHighScores;
    }
}
