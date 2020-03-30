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
 *     @ORM\UniqueConstraint(columns={"player_id", "sequence_number"})
 * })
 * @ORM\HasLifecycleCallbacks()
 */
class TrackedActivityFeedItem extends ActivityFeedItem
{
    /**
     * @var int
     *
     * @ORM\Column(type="integer")
     * @ORM\Id()
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @var int
     *
     * @ORM\Column(type="integer")
     */
    protected $sequenceNumber;

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

    public function __construct(ActivityFeedItem $originalItem, TrackedPlayer $player, int $sequenceNumber)
    {
        parent::__construct($originalItem->getTime(), $originalItem->getTitle(), $originalItem->getDescription());

        $this->player = $player;
        $this->sequenceNumber = $sequenceNumber;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getPlayer(): Player
    {
        return $this->player;
    }

    /**
     * @ORM\PostLoad()
     */
    public function postLoad(): void
    {
        parent::__construct($this->getTime(), $this->getTitle(), $this->getDescription());
    }

    public function getSequenceNumber(): int
    {
        return $this->sequenceNumber;
    }
}
