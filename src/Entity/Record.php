<?php

namespace App\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Villermen\RuneScape\HighScore\HighScoreSkillComparison;
use Villermen\RuneScape\Player;
use Villermen\RuneScape\Skill;

/**
 * @ORM\MappedSuperclass()
 * @ORM\Table(uniqueConstraints={
 *     @ORM\UniqueConstraint(columns={"id", "date", "player_id"})
 * })
 */
class Record
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
     * @var DateTime
     *
     * @ORM\Column(type="date")
     */
    protected $date;

    /**
     * @var Player
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\TrackedPlayer")
     * @ORM\JoinColumn(nullable=false)
     */
    protected $player;

    /**
     * @var Skill
     *
     * @ORM\Column(type="skill")
     */
    protected $skill;

    /**
     * @var int
     *
     * @ORM\Column(type="integer")
     */
    protected $xpGain;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean")
     */
    protected $oldSchool;

    /**
     * Constructs a DailyHighScore from a skill comparison.
     *
     * @param Player $player
     * @param HighScoreSkillComparison $comparison
     * @param bool $oldSchool
     */
    public function __construct(Player $player, HighScoreSkillComparison $comparison, bool $oldSchool)
    {
        $this->player = $player;
        $this->skill = $comparison->getSkill();
        $this->xpGain = $comparison->getXpDifference();
        $this->date = new DateTime();
        $this->oldSchool = $oldSchool;
    }

    /**
     * @return Player
     */
    public function getPlayer(): Player
    {
        return $this->player;
    }

    /**
     * @return Skill
     */
    public function getSkill(): Skill
    {
        return $this->skill;
    }

    /**
     * @return int
     */
    public function getXpGain(): int
    {
        return $this->xpGain;
    }

    /**
     * @return DateTime
     */
    public function getDate(): DateTime
    {
        return $this->date;
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }
}
