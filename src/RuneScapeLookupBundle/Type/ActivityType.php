<?php

namespace RuneScapeLookupBundle\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\IntegerType;
use Villermen\RuneScape\Activity;
use Villermen\RuneScape\Constants;
use Villermen\RuneScape\RuneScapeException;

class ActivityType extends IntegerType
{
    /** @inheritdoc */
    public function convertToDatabaseValue($value, AbstractPlatform $platform)
    {
        if (!$value instanceof Activity) {
            throw new RuneScapeException("Value to convert to database value is not of Activity class.");
        }

        return $value->getId();
    }

    /** @inheritdoc */
    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        return Constants::getActivity($value);
    }

    /** @inheritdoc */
    public function getName()
    {
        return "activity";
    }
}
