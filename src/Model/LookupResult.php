<?php

namespace App\Model;

use App\Entity\TrackedPlayer;
use Villermen\RuneScape\ActivityFeed\ActivityFeed;
use Villermen\RuneScape\HighScore\HighScore;
use Villermen\RuneScape\HighScore\HighScoreActivity;
use Villermen\RuneScape\HighScore\HighScoreComparison;
use Villermen\RuneScape\Player;

readonly class LookupResult
{
    public function __construct(
        public Player $player,
        public bool $oldSchool,
        public HighScore $highScore,
        public ?ActivityFeed $activityFeed,
        public ?TrackedPlayer $trackedPlayer,
        public ?HighScoreComparison $trainedToday,
        public ?HighScoreComparison $trainedYesterday,
        public ?HighScoreComparison $trainedWeek,
        public Records $records,
    ) {
    }

    public function isTracked(): bool
    {
        return $this->trackedPlayer?->isActive() ?? false;
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
