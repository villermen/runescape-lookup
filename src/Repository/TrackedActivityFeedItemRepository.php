<?php

namespace App\Repository;

use App\Entity\TrackedActivityFeedItem;
use App\Entity\TrackedPlayer;
use Doctrine\ORM\EntityRepository;
use Villermen\RuneScape\ActivityFeed\ActivityFeed;
use Villermen\RuneScape\ActivityFeed\ActivityFeedItem;
use Villermen\RuneScape\RuneScapeException;

class TrackedActivityFeedItemRepository extends EntityRepository
{
    /** @noinspection PhpDocMissingThrowsInspection */
    /**
     * @param TrackedPlayer $player
     * @return TrackedActivityFeedItem|null
     */
    public function findLatest(TrackedPlayer $player)
    {
        $qb = $this->createQueryBuilder("activity");

        /** @noinspection PhpUnhandledExceptionInspection */
        return $qb
            ->andWhere($qb->expr()->eq("activity.player", ":player"))
            ->setParameter("player", $player)
            ->addOrderBy($qb->expr()->desc("activity.time"))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Returns all tracked activity feed items from latest to earliest.
     *
     * @param TrackedPlayer $player
     * @param bool $mergeLive Fetch live activity feed and prepend.
     * @param int $liveTimeOut
     * @return ActivityFeed
     */
    public function findByPlayer(TrackedPlayer $player, $mergeLive = false, $liveTimeOut = 5): ActivityFeed
    {
        $qb = $this->createQueryBuilder("activity");

        $trackedActivityFeedItems = $qb
            ->andWhere($qb->expr()->eq("activity.player", ":player"))
            ->setParameter("player", $player)
            ->addOrderBy($qb->expr()->desc("activity.time"))
            ->getQuery()
            ->getResult();

        $activityFeed = new ActivityFeed($player, $trackedActivityFeedItems);

        if ($mergeLive) {
            try {
                $liveActivityFeed = $player->getActivityFeed($liveTimeOut);

                $activityFeed = $activityFeed->merge($liveActivityFeed);
            } catch (RuneScapeException $exception) {
                dump($exception);
            }
        }

        return $activityFeed;
    }
}
