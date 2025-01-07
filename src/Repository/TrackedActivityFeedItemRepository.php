<?php

namespace App\Repository;

use App\Entity\TrackedActivityFeedItem;
use App\Entity\TrackedPlayer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Villermen\RuneScape\ActivityFeed\ActivityFeed;
use Villermen\RuneScape\Exception\FetchFailedException;

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
     * Returns all tracked activity feed items from latest to earliest.
     *
     * @param bool $mergeLive Fetch live activity feed and prepend.
     */
    public function findByPlayer(TrackedPlayer $player, $mergeLive = false): ActivityFeed
    {
        $qb = $this->createQueryBuilder('activity');

        /** @var TrackedActivityFeedItem[] $trackedActivityFeedItems */
        $trackedActivityFeedItems = $qb
            ->andWhere($qb->expr()->eq('activity.player', ':player'))
            ->setParameter('player', $player)
            ->addOrderBy($qb->expr()->desc('activity.time'))
            ->getQuery()
            ->getResult();

        $activityFeed = new ActivityFeed($trackedActivityFeedItems);

        if ($mergeLive) {
            try {
                $liveActivityFeed = $player->getActivityFeed();

                $activityFeed = $activityFeed->merge($liveActivityFeed);
            } catch (FetchFailedException $exception) {
            }
        }

        return $activityFeed;
    }
}
