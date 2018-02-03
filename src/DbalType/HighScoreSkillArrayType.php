<?php

namespace App\DbalType;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use Villermen\RuneScape\HighScore\HighScoreSkill;
use Villermen\RuneScape\Skill;

class HighScoreSkillArrayType extends Type
{
    const DEFAULT_LENGTH = 10000;

    /** @inheritdoc */
    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform)
    {
        // Set default length
        if (!$fieldDeclaration["length"]) {
            $fieldDeclaration["length"] = self::DEFAULT_LENGTH;
        }

        return $platform->getClobTypeDeclarationSQL($fieldDeclaration);
    }

    /**
     * @param HighScoreSkill[] $value
     * @param AbstractPlatform $platform
     * @return mixed|string
     */
    public function convertToDatabaseValue($value, AbstractPlatform $platform)
    {
        $serializedSkills = [];
        foreach($value as $skill) {
            $serializedSkills[] = implode(",", [
                $skill->getSkill()->getId(),
                $skill->getLevel(),
                $skill->getXp(),
                (int)$skill->getRank()
            ]);
        }

        return implode(";", $serializedSkills);
    }

    /** @inheritdoc */
    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        $skills = [];
        foreach(explode(";", $value) as $skillData) {
            list($skillId, $level, $xp, $rank) = explode(",", $skillData);

            $skills[] = new HighScoreSkill(Skill::getSkill($skillId), $rank, $level, $xp);
        }

        return $skills;
    }

    /** @inheritdoc */
    public function getName()
    {
        return "high_score_skill_array";
    }
}
