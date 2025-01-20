<?php

namespace App\Entity;

use App\Repository\PersonalRecordRepository;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\UniqueConstraint;
use Villermen\RuneScape\HighScore\ActivityInterface;
use Villermen\RuneScape\HighScore\SkillInterface;

/**
 * @template T of SkillInterface|ActivityInterface = SkillInterface|ActivityInterface
 * @extends AbstractRecord<T>
 */
#[Entity(repositoryClass: PersonalRecordRepository::class)]
#[UniqueConstraint('unique_record', ['player_id', 'old_school', 'activity', 'type_id'])]
class PersonalRecord extends AbstractRecord
{
    #[Column(type: 'date_immutable')]
    protected \DateTimeImmutable $date;

    public function __construct(TrackedPlayer $player, SkillInterface|ActivityInterface $type, int $score, \DateTimeImmutable $date)
    {
        parent::__construct($player, $type, $score);

        $this->date = $date;
    }

    public function getDate(): \DateTimeImmutable
    {
        return $this->date;
    }
}
