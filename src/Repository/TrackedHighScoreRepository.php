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
        return $this->findOneBy([
            'date' => $date,
            'player' => $player,
            'highScore.oldSchool' => $oldSchool,
        ]);
    }

    public function hasAnyAtDate(\DateTimeInterface $date, TrackedPlayer $player): bool
    {
        return (bool)$this->findOneBy([
            'date' => $date,
            'player' => $player,
        ]);
    }
}
