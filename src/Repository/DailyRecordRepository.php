<?php

namespace App\Repository;

use App\Entity\DailyRecord;
use DateTime;
use Doctrine\ORM\EntityRepository;

class DailyRecordRepository extends EntityRepository
{
    /**
     * @param DateTime $date
     * @param bool $oldSchool
     * @return DailyRecord[]
     */
    public function findByDate(DateTime $date, bool $oldSchool): array
    {
        $qb = $this->createQueryBuilder("record");

        return $qb
            ->andWhere($qb->expr()->eq("record.date", ":date"))
            ->andWhere($qb->expr()->eq("record.oldSchool", ":oldSchool"))
            ->setParameter("date", (clone $date)->modify("midnight"))
            ->setParameter("oldSchool", $oldSchool)
            ->addOrderBy($qb->expr()->desc("record.xpGain"))
            ->getQuery()
            ->getResult();
    }
}
