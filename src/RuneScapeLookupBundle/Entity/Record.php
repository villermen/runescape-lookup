<?php

namespace RuneScapeLookupBundle\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Villermen\RuneScape\HighScore\HighScoreSkillComparison;
use Villermen\RuneScape\Player;

/**
 * TODO: Extend for activities
 *
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
     * @ORM\ManyToOne(targetEntity="RuneScapeLookupBundle\Entity\TrackedPlayer")
     * @ORM\JoinColumn(name="player")
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
     * @var int
     *
     * @ORM\Column(name="level_gain", type="integer")
     */
    protected $levelGain;

    /**
     * @var int
     *
     * @ORM\Column(name="rank_gain", type="integer")
     */
    protected $rankGain;

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
        $this->levelGain = $comparison->getLevelDifference();
        $this->rankGain = $comparison->getRankDifference();
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
