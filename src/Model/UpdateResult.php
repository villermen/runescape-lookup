<?php

namespace App\Model;

use App\Entity\TrackedPlayer;
use Villermen\RuneScape\HighScore\HighScoreComparison;

readonly class UpdateResult
{
    public function __construct(
        public TrackedPlayer $player,
        public ?HighScoreComparison $trainedRs3 = null,
        public ?HighScoreComparison $trainedOsrs = null,
    ) {
    }
}
