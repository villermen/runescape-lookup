<?php

namespace App\Entity\Embeddable;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Embeddable;
use Villermen\RuneScape\HighScore\HighScore;

/**
 * @template T of HighScore
 */
#[Embeddable]
class HighScoreType
{
    #[Column]
    protected bool $oldSchool;

    /** @var mixed[] */
    #[Column(type: 'json')]
    protected array $data;

    /** @var T|null */
    private ?HighScore $highScore = null;

    /**
     * @param T $highScore
     */
    public function __construct(bool $oldSchool, HighScore $highScore)
    {
        $this->oldSchool = $oldSchool;
        $this->data = $highScore->toArray();
        $this->highScore = $highScore;
    }

    /**
     * @return T
     */
    public function getHighScore(): HighScore
    {
        if ($this->highScore) {
            return $this->highScore;
        }

        // @phpstan-ignore argument.type
        $this->highScore = HighScore::fromArray($this->data, $this->oldSchool);
        return $this->highScore;
    }
}
