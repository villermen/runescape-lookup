<?php

namespace App\Repository;

use App\Entity\Record;
use App\Entity\TrackedPlayer;
use DateTime;
use Doctrine\ORM\EntityRepository;

class RecordRepository extends EntityRepository
{
    /**
     * Returns the most recent/highest record for each skill of the given player.
     * Records are indexed by skill id for convenience.
     *
     * @param TrackedPlayer $player
     * @param bool $oldSchool
     * @return Record[]
     */
    public function findHighestRecords(TrackedPlayer $player, bool $oldSchool): array
    {
        $qb = $this->createQueryBuilder("record");

        /** @var Record[] $records */
        $records = $qb
            ->andWhere($qb->expr()->eq("record.player", ":player"))
            ->andWhere($qb->expr()->eq("record.oldSchool", ":oldSchool"))
            ->setParameter("player", $player)
            ->setParameter("oldSchool", $oldSchool)
            ->addGroupBy("record.skill")
            ->andHaving($qb->expr()->eq("record.date", "MAX(record.date)"))
            ->getQuery()
            ->getResult();

        // Index by skill id
        $indexedRecords = [];
        foreach($records as $record) {
            $indexedRecords[$record->getSkill()->getId()] = $record;
        }

        return $indexedRecords;
    }

    /**
     * Returns daily records shared among players for the given date for all skills.
     *
     * @param DateTime $date
     * @param bool $oldSchool
     * @return Record[]
     */
    public function findDailyRecords(DateTime $date, bool $oldSchool): array
    {
        $qb = $this->createQueryBuilder("record");

        /** @var Record[] $records */
        $records = $qb
            ->andWhere($qb->expr()->eq("record.date", ":date"))
            ->andWhere($qb->expr()->gt("record.xpGain", 0))
            ->andWhere($qb->expr()->eq("record.oldSchool", ":oldSchool"))
            ->setParameter("date", (clone $date)->modify("midnight"))
            ->setParameter("oldSchool", $oldSchool)
            ->addGroupBy("record.skill")
            ->andHaving($qb->expr()->eq("record.xpGain", "MAX(record.xpGain)"))
            ->addOrderBy($qb->expr()->desc("record.xpGain"))
            ->addOrderBy($qb->expr()->asc("record.player"))
            ->getQuery()
            ->getResult();

        // Remove possible duplicates when multiple players have the same XP gain in the same skill for the same type of high score
        /** @var Record $previousRecord */
        $previousRecord = false;
        foreach($records as $key => $record) {
            if ($previousRecord &&
                $record->getSkill() === $previousRecord->getSkill()) {
                // Sorry mate, no hard feelings
                unset($records[$key]);
            }
        }

        return $records;
    }
}
