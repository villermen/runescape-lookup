<?php

namespace App\Repository;

use App\Entity\TrackedPlayer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TrackedPlayer>
 */
class TrackedPlayerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TrackedPlayer::class);
    }

    public function findByName(string $name): ?TrackedPlayer
    {
        return $this
            ->createQueryBuilder('player')
            ->andWhere("REPLACE(player.name, '_', ' ') = REPLACE(:name, '_', ' ')")
            ->setParameter('name', $name)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return TrackedPlayer[]
     */
    public function findAll(): array
    {
        $qb = $this->createQueryBuilder('player');

        return $qb
            ->addOrderBy($qb->expr()->desc('player.active'))
            ->addOrderBy($qb->expr()->asc('player.name'))
            ->getQuery()
            ->getResult();
    }

    /**
     * @return TrackedPlayer[]
     */
    public function findActive(): array
    {
        $qb = $this->createQueryBuilder('player');

        return $qb
            ->andWhere($qb->expr()->eq('player.active', ':active'))
            ->setParameter('active', true)
            ->getQuery()
            ->getResult();
    }
}
