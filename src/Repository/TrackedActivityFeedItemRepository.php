<?php

namespace App\Repository;

use App\Entity\TrackedActivityFeedItem;
use App\Entity\TrackedPlayer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Villermen\RuneScape\ActivityFeed\ActivityFeed;
use Villermen\RuneScape\ActivityFeed\ActivityFeedItem;

/**
 * @extends ServiceEntityRepository<TrackedActivityFeedItem>
 */
class TrackedActivityFeedItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TrackedActivityFeedItem::class);
    }

    public function findLast(TrackedPlayer $player): ?TrackedActivityFeedItem
    {
        return $this->findOneBy([
            'player' => $player,
        ], [
            'sequenceNumber' => 'DESC'
        ]);
    }

    /**
     * Returns all tracked activity feed items from latest to earliest as a feed.
     *
     * @return TrackedActivityFeedItem[]
     */
    public function findByPlayer(TrackedPlayer $player, ?int $limit = null): array
    {
        return $this->findBy([
            'player' => $player,
        ], [
            'sequenceNumber' => 'DESC',
        ], $limit);
    }
}
