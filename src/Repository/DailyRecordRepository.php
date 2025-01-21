<?php

namespace App\Repository;

use App\Entity\DailyRecord;
use App\Model\Records;
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
     * @return Records<DailyRecord>
     */
    public function findRecords(\DateTimeInterface $date, bool $oldSchool): Records
    {
        // findBy() but with join on player to greatly reduce amount of queries on overview.
        /** @var DailyRecord[] $records */
        $records = $this->createQueryBuilder('record')
            ->join('record.player', 'player')
            ->addSelect('player')
            ->andWhere('record.date = :date')
            ->setParameter('date', $date)
            ->andWhere('record.type.oldSchool = :oldSchool')
            ->setParameter('oldSchool', $oldSchool)
            ->orderBy('record.score', 'DESC')
            ->getQuery()
            ->getResult();

        return new Records($records);
    }

    public function hasAnyAtDate(\DateTimeInterface $date): bool
    {
        return (bool)$this->findOneBy([
            'date' => $date,
        ]);
    }
}
