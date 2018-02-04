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
     * @inheritdoc
     *
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
     * @param TrackedPlayer $player
     * @param bool $oldSchool
     */
    public function __construct(array $skills, TrackedPlayer $player, bool $oldSchool)
    {
        parent::__construct($skills);

        $this->player = $player;
        $this->oldSchool = $oldSchool;
        $this->date = new DateTime();
    }

    /**
     * @return TrackedPlayer
     */
    public function getPlayer(): TrackedPlayer
    {
        return $this->player;
    }

    /**
     * @return bool
     */
    public function isOldSchool(): bool
    {
        return $this->oldSchool;
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return DateTime
     */
    public function getDate(): DateTime
    {
        return $this->date;
    }

    /**
     * @ORM\PostLoad()
     */
    public function postLoad()
    {
        parent::__construct($this->getSkills());
    }
}
