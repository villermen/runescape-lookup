<?php


namespace RuneScapeLookupBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Villermen\RuneScape\HighScore\HighScoreSkillComparison;
use Villermen\RuneScape\Player;

/**
 * @ORM\Entity(repositoryClass="RuneScapeLookupBundle\Repository\DailyRecordRepository")
 * @ORM\Table(name="daily_record")
 */
class DailyRecord extends Record
{
    public function __construct(Player $player, HighScoreSkillComparison $comparison)
    {
        parent::__construct($player, $comparison);
    }
}
