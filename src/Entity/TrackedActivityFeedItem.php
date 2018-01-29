<?php

namespace App\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Villermen\RuneScape\ActivityFeed\ActivityFeedItem;
use Villermen\RuneScape\HighScore\HighScoreSkillComparison;
use Villermen\RuneScape\Player;

/**
 * @ORM\Entity(repositoryClass="App\Repository\TrackedActivityFeedItemRepository")
 * @ORM\Table(name="activity_feed_item")
 */
class TrackedActivityFeedItem extends ActivityFeedItem
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id()
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $databaseId;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="time", type="datetime")
     */
    protected $time;

    /**
     * @var Player
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\TrackedPlayer")
     * @ORM\JoinColumn(name="player", nullable=false)
     */
    protected $player;

    /**
     * @var string
     *
     * @ORM\Column(name="title", type="string")
     */
    protected $title;

    /**
     * @var int
     *
     * @ORM\Column(name="description", type="string")
     */
    protected $description;

    public function __construct(ActivityFeedItem $originalItem, TrackedPlayer $player)
    {
        parent::__construct($originalItem->getId(), $originalItem->getTime(), $originalItem->getTitle(),
            $originalItem->getDescription());

        $this->player = $player;
    }

    public function getDatabaseId(): int
    {
        return $this->databaseId;
    }

    /**
     * @return Player
     */
    public function getPlayer(): Player
    {
        return $this->player;
    }

    /**
     * @param Player $player
     *
     * @return TrackedActivityFeedItem
     */
    public function setPlayer(Player $player)
    {
        $this->player = $player;

        return $this;
    }
}
