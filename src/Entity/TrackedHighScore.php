<?php

namespace App\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Villermen\RuneScape\HighScore\HighScore;

/**
 * @ORM\Entity(repositoryClass="App\Repository\TrackedHighScoreRepository")
 * @ORM\Table(name="highscore", uniqueConstraints={
 *     @ORM\UniqueConstraint(columns={"player_id", "date", "old_school"})
 * })
 */
class TrackedHighScore extends HighScore
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
     * @ORM\Column(type="string", length=10000)
     */
    protected $data;

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
