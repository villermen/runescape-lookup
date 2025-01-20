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

    #[ManyToOne]
    #[JoinColumn(nullable: false)]
    protected TrackedPlayer $player;

    /** @var RecordType<T> */
    #[Embedded(columnPrefix: false)]
    protected RecordType $type;

    #[Column]
    protected int $score;

    /**
     * @param T $type
     */
    public function __construct(TrackedPlayer $player, SkillInterface|ActivityInterface $type, int $score)
    {
        $this->player = $player;
        $this->type = new RecordType($type);
        $this->score = $score;
    }

    public function getId(): ?int
    {
        return $this->id;
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
