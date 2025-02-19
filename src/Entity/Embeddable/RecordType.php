<?php

namespace App\Entity\Embeddable;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Embeddable;
use Villermen\RuneScape\HighScore\ActivityInterface;
use Villermen\RuneScape\HighScore\OsrsActivity;
use Villermen\RuneScape\HighScore\OsrsSkill;
use Villermen\RuneScape\HighScore\Rs3Activity;
use Villermen\RuneScape\HighScore\Rs3Skill;
use Villermen\RuneScape\HighScore\SkillInterface;

/**
 * @template T of SkillInterface|ActivityInterface = SkillInterface|ActivityInterface
 */
#[Embeddable]
class RecordType
{
    #[Column]
    protected bool $oldSchool;

    #[Column]
    protected bool $activity;

    #[Column]
    protected int $typeId;

    /**
     * @param T $type
     */
    public function __construct(SkillInterface|ActivityInterface $type)
    {
        $this->oldSchool = $type instanceof OsrsSkill || $type instanceof OsrsActivity;
        $this->activity = $type instanceof ActivityInterface;
        $this->typeId = $type->getId();
    }

    /**
     * @return T
     */
    public function getType(): SkillInterface|ActivityInterface
    {
        if ($this->oldSchool) {
            // @phpstan-ignore return.type (Inverse of ctor.)
            return $this->activity ? OsrsActivity::from($this->typeId) : OsrsSkill::from($this->typeId);
        }

        // @phpstan-ignore return.type
        return $this->activity ? Rs3Activity::from($this->typeId) : Rs3Skill::from($this->typeId);
    }
}
