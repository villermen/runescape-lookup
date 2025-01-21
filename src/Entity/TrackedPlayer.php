<?php

namespace App\Entity;

use App\Repository\TrackedPlayerRepository;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;
use Villermen\RuneScape\Player;

#[Entity(repositoryClass: TrackedPlayerRepository::class)]
#[Table(name: 'player')]
class TrackedPlayer
{
    #[Id]
    #[Column]
    #[GeneratedValue(strategy: 'AUTO')]
    protected ?int $id = null;

    #[Column(length: 12, unique: true)]
    protected string $name;

    #[Column]
    protected bool $active = true;

    // TODO: createdAt? (work back from high_score MIN(date))

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getPlayer(): Player
    {
        return new Player($this->getName());
    }
}
