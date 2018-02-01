<?php

namespace App\Repository;

use App\Entity\PersonalRecord;
use App\Entity\TrackedPlayer;
use Doctrine\ORM\EntityRepository;

class PersonalRecordRepository extends EntityRepository
{
    /**
     * Returns the most recent record for each skill of the given player.
     * The array's keys correspond to the skill id for lookup convenience.
     *
     * @param TrackedPlayer $player
     * @return PersonalRecord[]
     */
    public function findLatestRecords(TrackedPlayer $player): array
    {
        $qb = $this->createQueryBuilder("record");

        $records = $qb
            ->andWhere($qb->expr()->eq("record.player", ":player"))
            ->setParameter("player", $player)
            ->addGroupBy("record.skill")
            ->andHaving($qb->expr()->eq("record.date", "MAX(record.date)"))
            ->getQuery()
            ->getResult();

        array_walk($records, function(PersonalRecord $record, &$index) {
            $index = $record->getSkill()->getId();
        });

        return $records;
    }
}
