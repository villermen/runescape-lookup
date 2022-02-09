<?php

namespace App\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Villermen\RuneScape\Player;
use Villermen\RuneScape\Skill;

/**
 * @ORM\MappedSuperclass()
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

    public function __construct(Player $player, Skill $skill, int $xpGain, bool $oldSchool, DateTime $date)
    {
        $this->player = $player;
        $this->skill = $skill;
        $this->xpGain = $xpGain;
        $this->oldSchool = $oldSchool;
        $this->date = $date;
    }

    public function getPlayer(): Player
    {
        return $this->player;
    }

    public function getSkill(): Skill
    {
        return $this->skill;
    }

    public function getXpGain(): int
    {
        return $this->xpGain;
    }

    public function getDate(): DateTime
    {
        return $this->date;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function isOldSchool(): bool
    {
        return $this->oldSchool;
    }
}
