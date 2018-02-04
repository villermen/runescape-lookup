<?php

namespace App\Repository;

use App\Entity\TrackedPlayer;
use Doctrine\ORM\EntityRepository;

class TrackedPlayerRepository extends EntityRepository
{
    /** @noinspection PhpDocMissingThrowsInspection */
    /**
     * @param string $name
     * @return TrackedPlayer|null
     */
    public function findByName(string $name): ?TrackedPlayer
    {
        $qb = $this->createQueryBuilder("player");

        /** @noinspection PhpUnhandledExceptionInspection */
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
            ->orderBy($qb->expr()->desc("player.name"))
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
