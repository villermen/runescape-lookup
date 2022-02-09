<?php

namespace App\Repository;

use App\Entity\TrackedPlayer;
use Doctrine\ORM\EntityRepository;

class TrackedPlayerRepository extends EntityRepository
{
    public function findByName(string $name): ?TrackedPlayer
    {
        $qb = $this->createQueryBuilder("player");

        return $qb
            ->andWhere($qb->expr()->eq("player.name", ":name"))
            ->setParameter("name", $name)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return TrackedPlayer[]
     */
    public function findAll(): array
    {
        $qb = $this->createQueryBuilder("player");

        return $qb
            ->addOrderBy($qb->expr()->desc("player.active"))
            ->addOrderBy($qb->expr()->asc("player.name"))
            ->getQuery()
            ->getResult();
    }

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
