<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Villermen\RuneScape\HighScore\HighScoreComparisonSkill;
use Villermen\RuneScape\Player;

/**
 * @ORM\Entity(repositoryClass="App\Repository\DailyRecordRepository")
 */
class DailyRecord extends Record
{
    public function __construct(Player $player, HighScoreComparisonSkill $comparison)
    {
        parent::__construct($player, $comparison);
    }
}
