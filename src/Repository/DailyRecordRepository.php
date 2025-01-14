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
    public function findRecords(bool $oldSchool): Records
    {
        return new Records($this->findBy([
            'type.oldSchool' => $oldSchool,
        ], [
            'score' => 'DESC'
        ]));
    }
}
