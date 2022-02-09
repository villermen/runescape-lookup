<?php

namespace App\DbalType;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\IntegerType;
use Exception;
use Villermen\RuneScape\Activity;

class ActivityType extends IntegerType
{
    public function convertToDatabaseValue($value, AbstractPlatform $platform): int
    {
        if (!$value instanceof Activity) {
            throw new Exception("Value to convert to database value is not of Activity class.");
        }

        return $value->getId();
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): Activity
    {
        return Activity::getActivity($value);
    }

    public function getName(): string
    {
        return "activity";
    }
}
