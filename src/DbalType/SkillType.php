<?php

namespace App\DbalType;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\IntegerType;
use Exception;
use Villermen\RuneScape\Skill;

class SkillType extends IntegerType
{
    /** @inheritdoc */
    public function convertToDatabaseValue($value, AbstractPlatform $platform)
    {
        if (!$value instanceof Skill) {
            throw new Exception("Value to convert to database value is not of Skill class.");
        }

        return $value->getId();
    }

    /** @inheritdoc */
    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        return Skill::getSkill($value);
    }

    /** @inheritdoc */
    public function getName()
    {
        return "skill";
    }
}
