<?php

namespace App\Repository;

use App\Entity\PersonalRecord;
use App\Entity\TrackedPlayer;
use App\Model\RecordCollection;
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
     * @return RecordCollection<PersonalRecord>
     */
    public function findRecords(TrackedPlayer $player, bool $oldSchool): RecordCollection
    {
        return new RecordCollection($this->findBy([
            'player' => $player,
            'type.oldSchool' => $oldSchool,
        ]));
    }
}
