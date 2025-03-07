<?php

namespace App\Entity;

use App\Repository\PersonalRecordRepository;
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
    public function updateScore(int $score, \DateTimeImmutable $date): void
    {
        $this->score = $score;
        $this->date = $date;
    }
}
