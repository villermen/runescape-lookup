<?php

namespace App\Entity;

use App\Entity\Embeddable\RecordType;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Embedded;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\MappedSuperclass;
use Villermen\RuneScape\HighScore\ActivityInterface;
use Villermen\RuneScape\HighScore\SkillInterface;

/**
 * @template T of SkillInterface|ActivityInterface
 */
#[MappedSuperclass]
abstract class AbstractRecord
{
    #[Id]
    #[Column]
    #[GeneratedValue(strategy: 'AUTO')]
    protected ?int $id = null;

    #[Column(type: 'date_immutable')]
    protected \DateTimeImmutable $date;

    #[ManyToOne]
    #[JoinColumn(nullable: false)]
    protected TrackedPlayer $player;

    /** @var RecordType<T> */
    #[Embedded]
    protected RecordType $type;

    #[Column]
    protected int $score;

    /**
     * @param T $type
     */
    public function __construct(\DateTimeImmutable $date, TrackedPlayer $player, SkillInterface|ActivityInterface $type, int $score)
    {
        $this->date = $date;
        $this->player = $player;
        $this->type = new RecordType($type);
        $this->score = $score;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDate(): \DateTimeImmutable
    {
        return $this->date;
    }

    public function getPlayer(): TrackedPlayer
    {
        return $this->player;
    }

    /**
     * @return T
     */
    public function getType(): SkillInterface|ActivityInterface
    {
        return $this->type->getType();
    }

    public function getScore(): int
    {
        return $this->score;
    }
}
