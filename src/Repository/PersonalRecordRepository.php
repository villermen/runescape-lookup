<?php

namespace App\Repository;

use App\Entity\PersonalRecord;
use App\Entity\TrackedPlayer;
use App\Model\Records;
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
     * @return Records<PersonalRecord>
     */
    public function findRecords(TrackedPlayer $player, bool $oldSchool): Records
    {
        return new Records($this->findBy([
            'player' => $player,
            'type.oldSchool' => $oldSchool,
        ]));
    }
}
