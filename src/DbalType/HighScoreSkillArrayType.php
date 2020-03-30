<?php

namespace App\DbalType;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use Villermen\RuneScape\HighScore\HighScoreSkill;
use Villermen\RuneScape\Skill;

class HighScoreSkillArrayType extends Type
{
    const DEFAULT_LENGTH = 10000;

    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform): string
    {
        // Set default length
        if (!$fieldDeclaration["length"]) {
            $fieldDeclaration["length"] = self::DEFAULT_LENGTH;
        }

        return $platform->getClobTypeDeclarationSQL($fieldDeclaration);
    }

    /**
     * @param HighScoreSkill[] $value
     */
    public function convertToDatabaseValue($value, AbstractPlatform $platform): string
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

    /**
     * @param string $value
     * @return HighScoreSkill[]
     */
    public function convertToPHPValue($value, AbstractPlatform $platform): array
    {
        $skills = [];
        foreach(explode(";", $value) as $skillData) {
            list($skillId, $level, $xp, $rank) = explode(",", $skillData);

            $skills[] = new HighScoreSkill(Skill::getSkill($skillId), $rank, $level, $xp);
        }

        return $skills;
    }

    public function getName(): string
    {
        return "high_score_skill_array";
    }
}
