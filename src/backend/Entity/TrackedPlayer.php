<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Villermen\RuneScape\Exception\FetchFailedException;
use Villermen\RuneScape\Exception\RuneScapeException;
use Villermen\RuneScape\Player;

/**
 * @ORM\Entity(repositoryClass="App\Repository\TrackedPlayerRepository")
 * @ORM\Table(name="player")
 * @ORM\HasLifecycleCallbacks()
 */
class TrackedPlayer extends Player
{
    /**
     * @ORM\Column(type="integer")
     * @ORM\Id()
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @ORM\Column(type="string", length=12, unique=true)
     */
    protected $name;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean")
     */
    protected $active = true;

    public function __construct(string $name)
    {
        parent::__construct($name);
    }

    /**
     * @throws FetchFailedException
     */
    public function getTrackedHighScore(bool $oldSchool = false, int $timeOut = 5): TrackedHighScore
    {
        $this->dataFetcher->setTimeOut($timeOut);

        $highScore = $oldSchool ? $this->getSkillHighScore() : $this->getOldSchoolSkillHighScore();
        return new TrackedHighScore($highScore->getSkills(), $this, $oldSchool);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): TrackedPlayer
    {
        $this->active = $active;

        return $this;
    }

    /**
     * @ORM\PostLoad()
     */
    public function postLoad(): void
    {
        parent::__construct($this->getName());
    }
}
