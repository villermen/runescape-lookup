<?php

namespace App\Repository;

use App\Entity\PersonalRecord;
use App\Entity\TrackedPlayer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PersonalRecord>
 */
class PersonalRecordRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PersonalRecord::class);
    }

    /**
     * Returns the most recent/highest record for each skill of the given player. Records are indexed by skill id for
     * convenience.
     *
     * @return PersonalRecord[]
     */
    public function findHighestRecords(TrackedPlayer $player, bool $oldSchool): array
    {
        $qb = $this->createQueryBuilder('record');

        return $qb
            ->andWhere($qb->expr()->eq('record.player', ':player'))
            ->setParameter('player', $player)
            ->andWhere($qb->expr()->eq('record.type.oldSchool', ':oldSchool'))
            ->setParameter('oldSchool', $oldSchool)
            ->addGroupBy('record.skill')
            ->addGroupBy('record.id')
            ->andHaving($qb->expr()->eq('record.score', 'MAX(record.score)'))
            ->getQuery()
            ->getResult();
    }
}
