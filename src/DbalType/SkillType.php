<?php

namespace App\DbalType;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\IntegerType;
use Exception;
use Villermen\RuneScape\Skill;

class SkillType extends IntegerType
{
    public function convertToDatabaseValue($value, AbstractPlatform $platform): string
    {
        if (!$value instanceof Skill) {
            throw new Exception("Value to convert to database value is not of Skill class.");
        }

        return $value->getId();
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): Skill
    {
        return Skill::getSkill($value);
    }

    public function getName(): string
    {
        return "skill";
    }
}
