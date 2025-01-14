<?php

namespace App\Model;

use App\Entity\TrackedPlayer;
use Villermen\RuneScape\ActivityFeed\ActivityFeed;
use Villermen\RuneScape\HighScore\ActivityInterface;
use Villermen\RuneScape\HighScore\HighScore;
use Villermen\RuneScape\HighScore\HighScoreActivity;
use Villermen\RuneScape\HighScore\HighScoreComparison;
use Villermen\RuneScape\Player;

class LookupResult
{
    public function __construct(
        public readonly Player $player,
        public readonly bool $oldSchool,
        public readonly HighScore $highScore,
        public readonly ?ActivityFeed $activityFeed,
        public readonly ?TrackedPlayer $trackedPlayer,
        public readonly ?HighScoreComparison $trainedToday,
        public readonly ?HighScoreComparison $trainedYesterday,
        public readonly ?HighScoreComparison $trainedWeek,
        public readonly Records $records,
    ) {
    }

    public function isTracked(): bool
    {
        return !$this->trackedPlayer?->isActive();
    }

    /**
     * @return HighScoreActivity[]
     */
    public function getActivitiesWithScore(): array
    {
        $activitiesWithScore = [];
        foreach ($this->highScore->getActivities() as $activity) {
            if ($activity->score === null) {
                continue;
            }

            $activitiesWithScore[] = $activity;
        }

        return $activitiesWithScore;
    }

    /**
     * @return 'osrs'|'rs3'
     */
    public function getGame(): string
    {
        return $this->oldSchool ? 'osrs' : 'rs3';
    }
}
