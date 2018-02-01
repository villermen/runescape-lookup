<?php

namespace App\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Villermen\RuneScape\ActivityFeed\ActivityFeedItem;
use Villermen\RuneScape\HighScore\HighScoreSkillComparison;
use Villermen\RuneScape\Player;

/**
 * @ORM\Entity(repositoryClass="App\Repository\TrackedActivityFeedItemRepository")
 * @ORM\Table(name="activity_feed_item", uniqueConstraints={
 *     @ORM\UniqueConstraint(columns={"player_id", "time"})
 * })
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
     * @ORM\ManyToOne(targetEntity="App\Entity\TrackedPlayer")
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

        // Set time to current time, which is more accurate than the feed's time in most cases
        // Skip when the difference in time is greater than a day, to prevent old items from being given the wrong time
        $currentTime = new DateTime();
        if ($currentTime->getTimestamp() - $originalItem->getTime()->getTimestamp() < 60 * 60 * 24) {
            $this->time = $currentTime;
        }
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
     * @param DateTime $time
     * @return TrackedActivityFeedItem
     */
    public function setTime(DateTime $time): TrackedActivityFeedItem
    {
        $this->time = $time;

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
