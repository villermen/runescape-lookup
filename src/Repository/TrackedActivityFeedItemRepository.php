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
        $qb = $this->createQueryBuilder('activity');

        return $qb
            ->andWhere($qb->expr()->eq('activity.player', ':player'))
            ->setParameter('player', $player)
            ->addOrderBy($qb->expr()->desc('activity.sequenceNumber'))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Returns all tracked activity feed items from latest to earliest as a feed.
     */
    public function findFeed(TrackedPlayer $player): ActivityFeed
    {
        return $this->createFeedFromTrackedItems($this->findBy([
            'player' => $player,
        ], [
            'time' => 'DESC',
        ]));
    }

    /**
     * @param TrackedActivityFeedItem[] $trackedItems
     */
    private function createFeedFromTrackedItems(array $trackedItems): ActivityFeed
    {
        return new ActivityFeed(array_map(
            fn (TrackedActivityFeedItem $trackedItem): ActivityFeedItem => new ActivityFeedItem(
                $trackedItem->getTime(),
                $trackedItem->getTitle(),
                $trackedItem->getDescription(),
            ),
            $trackedItems
        ));
    }
}
