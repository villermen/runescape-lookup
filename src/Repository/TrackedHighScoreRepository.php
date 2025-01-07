<?php

namespace App\Repository;

use App\Entity\TrackedHighScore;
use App\Entity\TrackedPlayer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Villermen\RuneScape\HighScore\OsrsHighScore;
use Villermen\RuneScape\HighScore\Rs3HighScore;

/**
 * @extends ServiceEntityRepository<TrackedHighScore>
 */
class TrackedHighScoreRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TrackedHighScore::class);
    }

    /**
     * @return TrackedHighScore<($oldSchool is true ? OsrsHighScore : Rs3HighScore)>
     */
    public function findByDate(\DateTimeInterface $date, TrackedPlayer $player, bool $oldSchool): ?TrackedHighScore
    {
        $qb = $this->createQueryBuilder('highScore');

        return $qb
            ->andWhere($qb->expr()->eq('highScore.date', ':date'))
            ->setParameter('date', $date)
            ->andWhere($qb->expr()->eq('highScore.player', ':player'))
            ->setParameter('player', $player)
            ->andWhere($qb->expr()->eq('highScore.highScore.oldSchool', ':oldSchool'))
            ->setParameter('oldSchool', $oldSchool)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
