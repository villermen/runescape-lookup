<?php

namespace App\Repository;

use App\Entity\DailyRecord;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DailyRecord>
 */
class DailyRecordRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DailyRecord::class);
    }

    /**
     * @return DailyRecord[]
     */
    public function findByDate(\DateTimeInterface $date, bool $oldSchool): array
    {

        $qb = $this->createQueryBuilder('record');

        return $qb
            ->andWhere($qb->expr()->eq('record.date', ':date'))
            ->setParameter('date', $date)
            ->andWhere($qb->expr()->eq('record.type.oldSchool', ':oldSchool'))
            ->setParameter('oldSchool', $oldSchool)
            ->addOrderBy($qb->expr()->desc('record.score'))
            ->getQuery()
            ->getResult();
    }
}
