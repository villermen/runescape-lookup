<?php

namespace App\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\IntegerType;
use Villermen\RuneScape\Constants;
use Villermen\RuneScape\RuneScapeException;
use Villermen\RuneScape\Skill;

class SkillType extends IntegerType
{
    /** @inheritdoc */
    public function convertToDatabaseValue($value, AbstractPlatform $platform)
    {
        if (!$value instanceof Skill) {
            throw new RuneScapeException("Value to convert to database value is not of Skill class.");
        }

        return $value->getId();
    }

    /** @inheritdoc */
    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        return Constants::getSkill($value);
    }

    /** @inheritdoc */
    public function getName()
    {
        return "skill";
    }
}
