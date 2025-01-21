<?php

namespace App\Entity;

use App\Repository\DailyRecordRepository;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\UniqueConstraint;
use Villermen\RuneScape\HighScore\ActivityInterface;
use Villermen\RuneScape\HighScore\SkillInterface;

/**
 * @template T of SkillInterface|ActivityInterface = SkillInterface|ActivityInterface
 * @extends AbstractRecord<T>
 */
#[Entity(repositoryClass: DailyRecordRepository::class)]
#[UniqueConstraint('unique_record', ['date', 'old_school', 'activity', 'type_id'])]
class DailyRecord extends AbstractRecord
{
}
