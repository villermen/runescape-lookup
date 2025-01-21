<?php

namespace App\Entity;

use App\Entity\Embeddable\HighScoreType;
use App\Repository\TrackedHighScoreRepository;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Embedded;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\Table;
use Doctrine\ORM\Mapping\UniqueConstraint;
use Villermen\RuneScape\HighScore\HighScore;

/**
 * @template T of HighScore = HighScore
 */
#[Entity(repositoryClass: TrackedHighScoreRepository::class)]
#[Table(name: 'high_score')]
#[UniqueConstraint('unique_high_score', ['player_id', 'old_school', 'date'])]
class TrackedHighScore
{
    #[Id]
    #[Column]
    #[GeneratedValue(strategy: 'AUTO')]
    protected ?int $id = null;

    #[ManyToOne]
    #[JoinColumn(nullable: false)]
    protected TrackedPlayer $player;

    #[Column(type: 'date_immutable')]
    protected \DateTimeImmutable $date;

    /** @var HighScoreType<T> */
    #[Embedded(columnPrefix: false)]
    protected HighScoreType $highScore;

    /**
     * @param T $highScore
     */
    public function __construct(TrackedPlayer $player, \DateTimeImmutable $date, bool $oldSchool, HighScore $highScore)
    {
        $this->player = $player;
        $this->date = $date;
        $this->highScore = new HighScoreType($oldSchool, $highScore);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPlayer(): TrackedPlayer
    {
        return $this->player;
    }

    public function getDate(): \DateTimeImmutable
    {
        return $this->date;
    }

    /**
     * @return T
     */
    public function getHighScore(): HighScore
    {
        return $this->highScore->getHighScore();
    }
}
