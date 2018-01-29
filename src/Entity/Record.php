<?php

namespace App\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Villermen\RuneScape\HighScore\HighScoreSkillComparison;
use Villermen\RuneScape\Player;

/**
 * @ORM\MappedSuperclass()
 */
class Record
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id()
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="date", type="date")
     */
    protected $date;

    /**
     * @var Player
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\TrackedPlayer")
     * @ORM\JoinColumn(name="player", nullable=false)
     */
    protected $player;

    /**
     * @var int
     *
     * @ORM\Column(name="skill", type="skill")
     */
    protected $skill;

    /**
     * @var int
     *
     * @ORM\Column(name="xp_gain", type="integer")
     */
    protected $xpGain;

    /**
     * Constructs a DailyHighScore from a skill comparison.
     *
     * @param Player $player
     * @param HighScoreSkillComparison $comparison
     */
    public function __construct(Player $player, HighScoreSkillComparison $comparison)
    {
        $this->player = $player;
        $this->skill = $comparison->getSkill();
        $this->xpGain = $comparison->getXpDifference();
        $this->date = new DateTime();
    }

    /**
     * @return Player
     */
    public function getPlayer(): Player
    {
        return $this->player;
    }

    /**
     * @return int
     */
    public function getSkill(): int
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
     * @return int
     */
    public function getLevelGain(): int
    {
        return $this->levelGain;
    }

    /**
     * @return int
     */
    public function getRankGain(): int
    {
        return $this->rankGain;
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
