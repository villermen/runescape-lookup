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
     * @inheritdoc
     *
     * @ORM\Column(type="string", length=12, unique=true)
     */
    protected $name;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean")
     */
    protected $active = true;

    /**
     * @param string $name
     * @throws RuneScapeException
     */
    public function __construct(string $name)
    {
        parent::__construct($name);
    }

    /**
     * @param bool $oldSchool
     * @param int $timeOut
     * @return TrackedHighScore
     * @throws FetchFailedException
     */
    public function getTrackedHighScore($oldSchool = false, $timeOut = 5): TrackedHighScore
    {
        $this->dataFetcher->setTimeOut($timeOut);

        $highScore = $oldSchool ? $this->getSkillHighScore() : $this->getOldSchoolSkillHighScore();
        $trackedHighScore = new TrackedHighScore($highScore->getSkills(), $this, $oldSchool);

        return $trackedHighScore;
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * @param bool $active
     *
     * @return TrackedPlayer
     */
    public function setActive(bool $active): TrackedPlayer
    {
        $this->active = $active;

        return $this;
    }

    /**
     * @ORM\PostLoad()
     * @throws RuneScapeException
     */
    public function postLoad()
    {
        parent::__construct($this->getName());
    }
}
