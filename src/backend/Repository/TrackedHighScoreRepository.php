<?php

namespace App\Repository;

use App\Entity\TrackedHighScore;
use App\Entity\TrackedPlayer;
use DateTime;
use Doctrine\ORM\EntityRepository;

class TrackedHighScoreRepository extends EntityRepository
{
    public function findByDate(DateTime $date, TrackedPlayer $player, bool $oldSchool): ?TrackedHighScore
    {
        $qb = $this->createQueryBuilder("highScore");

        return $qb
            ->andWhere($qb->expr()->eq("highScore.date", ":date"))
            ->andWhere($qb->expr()->eq("highScore.player", ":player"))
            ->andWhere($qb->expr()->eq("highScore.oldSchool", ":oldSchool"))
            ->setParameter("date", (clone $date)->modify("midnight"))
            ->setParameter("player", $player)
            ->setParameter("oldSchool", $oldSchool)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
