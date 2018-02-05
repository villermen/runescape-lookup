<?php

namespace App\Repository;

use App\Entity\PersonalRecord;
use App\Entity\TrackedPlayer;
use Doctrine\ORM\EntityRepository;

class PersonalRecordRepository extends EntityRepository
{
    /**
     * Returns the most recent/highest record for each skill of the given player.
     * Records are indexed by skill id for convenience.
     *
     * @param TrackedPlayer $player
     * @param bool $oldSchool
     * @return PersonalRecord[]
     */
    public function findHighestRecords(TrackedPlayer $player, bool $oldSchool): array
    {
        $qb = $this->createQueryBuilder("record");

        /** @var PersonalRecord[] $records */
        $records = $qb
            ->andWhere($qb->expr()->eq("record.player", ":player"))
            ->andWhere($qb->expr()->eq("record.oldSchool", ":oldSchool"))
            ->setParameter("player", $player)
            ->setParameter("oldSchool", $oldSchool)
            ->addGroupBy("record.skill")
            ->addGroupBy("record.id")
            ->andHaving($qb->expr()->eq("record.xpGain", "MAX(record.xpGain)"))
            ->getQuery()
            ->getResult();

        // Index by skill id
        $indexedRecords = [];
        foreach($records as $record) {
            $indexedRecords[$record->getSkill()->getId()] = $record;
        }

        return $indexedRecords;
    }
}
