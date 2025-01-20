<?php

namespace App\Entity;

use App\Repository\TrackedActivityFeedItemRepository;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\Table;
use Doctrine\ORM\Mapping\UniqueConstraint;
use Villermen\RuneScape\ActivityFeed\ActivityFeedItem;

#[Entity(repositoryClass: TrackedActivityFeedItemRepository::class)]
#[Table(name: 'activity_feed_item')]
#[UniqueConstraint('unique_sequence', ['player_id', 'sequence_number'])]
class TrackedActivityFeedItem
{
    #[Id]
    #[Column]
    #[GeneratedValue(strategy: 'AUTO')]
    protected ?int $id = null;

    #[ManyToOne]
    #[JoinColumn(nullable: false)]
    protected TrackedPlayer $player;

    #[Column]
    protected int $sequenceNumber;

    #[Column(type: 'datetime_immutable')]
    protected \DateTimeImmutable $time;

    #[Column(length: 1000)]
    protected string $title;

    #[Column(length: 10000)]
    protected string $description;

    public function __construct(ActivityFeedItem $originalItem, TrackedPlayer $player, int $sequenceNumber)
    {
        $this->player = $player;
        $this->sequenceNumber = $sequenceNumber;
        $this->time = $originalItem->time;
        $this->title = $originalItem->title;
        $this->description = $originalItem->description;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPlayer(): TrackedPlayer
    {
        return $this->player;
    }

    public function getSequenceNumber(): int
    {
        return $this->sequenceNumber;
    }

    public function getTime(): \DateTimeImmutable
    {
        return $this->time;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): string
    {
        return $this->description;
    }
}
