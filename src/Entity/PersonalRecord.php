<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Villermen\RuneScape\HighScore\HighScoreSkillComparison;
use Villermen\RuneScape\Player;

/**
 * @ORM\Entity(repositoryClass="App\Repository\PersonalRecordRepository")
 * @ORM\Table(name="personal_record")
 */
class PersonalRecord extends Record
{
    public function __construct(Player $player, HighScoreSkillComparison $comparison)
    {
        parent::__construct($player, $comparison);
    }
}
