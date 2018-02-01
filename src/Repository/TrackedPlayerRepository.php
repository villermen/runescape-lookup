<?php

namespace App\Repository;

use App\Entity\TrackedPlayer;
use Doctrine\ORM\EntityRepository;

class TrackedPlayerRepository extends EntityRepository
{
    /**
     * @return TrackedPlayer[]
     */
    public function findActive(): array
    {
        $qb = $this->createQueryBuilder("player");

        return $qb
            ->andWhere($qb->expr()->eq("player.active", ":active"))
            ->setParameter("active", true)
            ->getQuery()
            ->getResult();
    }
}
