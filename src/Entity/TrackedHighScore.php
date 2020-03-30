<?php

namespace App\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Villermen\RuneScape\HighScore\HighScoreSkill;
use Villermen\RuneScape\HighScore\SkillHighScore;

/**
 * @ORM\Entity(repositoryClass="App\Repository\TrackedHighScoreRepository")
 * @ORM\Table(name="high_score", uniqueConstraints={
 *     @ORM\UniqueConstraint(columns={"player_id", "date", "old_school"})
 * })
 * @ORM\HasLifecycleCallbacks()
 */
class TrackedHighScore extends SkillHighScore
{
    /**
     * @var int
     *
     * @ORM\Column(type="integer")
     * @ORM\Id()
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @ORM\Column(type="high_score_skill_array")
     */
    protected $skills;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean")
     */
    protected $oldSchool;

    /**
     * @var DateTime
     *
     * @ORM\Column(type="date")
     */
    protected $date;

    /**
     * @var TrackedPlayer
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\TrackedPlayer")
     * @ORM\JoinColumn(nullable=false)
     */
    protected $player;

    /**
     * @param HighScoreSkill[] $skills
     */
    public function __construct(array $skills, TrackedPlayer $player, bool $oldSchool)
    {
        parent::__construct($skills);

        $this->player = $player;
        $this->oldSchool = $oldSchool;
        $this->date = new DateTime();
    }

    public function getPlayer(): TrackedPlayer
    {
        return $this->player;
    }

    public function isOldSchool(): bool
    {
        return $this->oldSchool;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getDate(): DateTime
    {
        return $this->date;
    }

    /**
     * @ORM\PostLoad()
     */
    public function postLoad(): void
    {
        parent::__construct($this->getSkills());
    }
}
