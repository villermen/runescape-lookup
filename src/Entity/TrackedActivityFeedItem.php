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
 * @ORM\HasLifecycleCallbacks()
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
     * @var string
     *
     * @ORM\Column(name="guid", type="string")
     */
    protected $id;

    /**
     * @var DateTime
     *
     * @ORM\Column(type="datetime")
     */
    protected $time;

    /**
     * @var Player
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\TrackedPlayer", inversedBy="trackedActivityFeedItems")
     * @ORM\JoinColumn(nullable=false)
     */
    protected $player;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=1000)
     */
    protected $title;

    /**
     * @var int
     *
     * @ORM\Column(type="string", length=10000)
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

    /**
     * @ORM\PostLoad()
     */
    public function postLoad()
    {
        parent::__construct($this->getId(), $this->getTime(), $this->getTitle(), $this->getDescription());
    }
}
