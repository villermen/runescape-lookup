<?php

namespace App\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Villermen\RuneScape\HighScore\HighScore;

/**
 * @ORM\Entity(repositoryClass="App\Repository\TrackedHighScoreRepository")
 * @ORM\Table(name="highscore", uniqueConstraints={
 *     @ORM\UniqueConstraint(columns={"player", "date", "oldSchool"})
 * })
 */
class TrackedHighScore extends HighScore
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
     * @inheritdoc
     *
     * @ORM\Column(name="data", type="string")
     */
    protected $data;

    /**
     * @var bool
     *
     * @ORM\Column(name="oldschool", type="boolean")
     */
    protected $oldSchool;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="date", type="date")
     */
    protected $date;

    /**
     * @var TrackedPlayer
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\TrackedPlayer", inversedBy="trackedHighScores")
     * @ORM\JoinColumn(name="player")
     */
    protected $player;

    /**
     * @param string $data
     * @param TrackedPlayer $player
     * @param bool $oldSchool
     * @throws \Villermen\RuneScape\RuneScapeException
     */
    public function __construct(string $data, TrackedPlayer $player, bool $oldSchool)
    {
        parent::__construct($player, $data);

        $this->data = $data;

        $this->player = $player;
        $this->oldSchool = $oldSchool;
        $this->date = new DateTime();
    }

    /**
     * @return TrackedPlayer
     */
    public function getTrackedPlayer(): TrackedPlayer
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
}
