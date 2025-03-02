<?php

namespace App\Service;

use App\Entity\PersonalRecord;
use App\Entity\TrackedActivityFeedItem;
use App\Entity\TrackedHighScore;
use App\Entity\TrackedPlayer;
use App\Model\LookupResult;
use App\Model\Records;
use App\Model\UpdateResult;
use App\Repository\PersonalRecordRepository;
use App\Repository\TrackedActivityFeedItemRepository;
use App\Repository\TrackedHighScoreRepository;
use App\Repository\TrackedPlayerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Villermen\RuneScape\ActivityFeed\ActivityFeed;
use Villermen\RuneScape\ActivityFeed\ActivityFeedItem;
use Villermen\RuneScape\Exception\FetchFailedException;
use Villermen\RuneScape\HighScore\HighScore;
use Villermen\RuneScape\HighScore\HighScoreComparison;
use Villermen\RuneScape\HighScore\OsrsHighScore;
use Villermen\RuneScape\HighScore\Rs3HighScore;
use Villermen\RuneScape\Player;
use Villermen\RuneScape\PlayerData\RuneMetricsData;
use Villermen\RuneScape\Service\PlayerDataFetcher;

class LookupService
{
    public function __construct(
        private readonly PlayerDataFetcher $playerDataFetcher,
        private readonly TrackedHighScoreRepository $trackedHighScoreRepository,
        private readonly PersonalRecordRepository $personalRecordRepository,
        private readonly TimeKeeper $timeKeeper,
        private readonly TrackedPlayerRepository $trackedPlayerRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly TrackedActivityFeedItemRepository $trackedActivityFeedItemRepository,
        #[Autowire(param: 'app.readonly')]
        private readonly bool $readonly,
    ) {
    }

    public function lookUpPlayer(Player $player, bool $oldSchool): ?LookupResult
    {
        $highScore = null;
        $activityFeed = null;
        $displayName = null;

        try {
            $highScore = $this->playerDataFetcher->fetchIndexLite($player, oldSchool: $oldSchool);
        } catch (FetchFailedException) {
        }

        // Always check RuneMetrics for RS3 players because it contains activity feed.
        if (!$oldSchool) {
            try {
                $runeMetrics = $this->playerDataFetcher->fetchRuneMetrics($player);
                // Use RuneMetrics as fallback (index_lite includes activities).
                $highScore = $highScore ?? $runeMetrics->highScore;
                $activityFeed = $runeMetrics->activityFeed;
                $displayName = $runeMetrics->displayName;
            } catch (FetchFailedException) {
            }
        }

        if (!$highScore) {
            return null;
        }

        $trainedToday = null;
        $trainedYesterday = null;
        $trainedWeek = null;
        $records = new Records([]);

        $trackedPlayer = $this->isReadonly() ? null : $this->trackedPlayerRepository->findByName($player->getName());
        if ($trackedPlayer) {
            $displayName ??= $trackedPlayer->getName();

            $highScoreToday = $this->trackedHighScoreRepository->findByDate(
                $this->timeKeeper->getUpdateTime(),
                $trackedPlayer,
                $oldSchool
            )?->getHighScore();
            $highScoreYesterday = $this->trackedHighScoreRepository->findByDate(
                $this->timeKeeper->getUpdateTime(-1),
                $trackedPlayer,
                $oldSchool
            )?->getHighScore();
            $highScoreWeek = $this->trackedHighScoreRepository->findByDate(
                $this->timeKeeper->getUpdateTime(-7),
                $trackedPlayer,
                $oldSchool
            )?->getHighScore();
            $trainedToday = $highScoreToday ? $highScore->compareTo($highScoreToday) : null;
            $trainedYesterday = $highScoreYesterday ? $highScoreToday?->compareTo($highScoreYesterday) : null;
            $trainedWeek = $highScoreWeek ? $highScoreToday?->compareTo($highScoreWeek) : null;
            $records = $this->personalRecordRepository->findRecords($trackedPlayer, $oldSchool);

            if (!$oldSchool) {
                $trackedActivityFeed = new ActivityFeed(array_map(
                    fn (TrackedActivityFeedItem $item): ActivityFeedItem => $item->getItem(),
                    $this->trackedActivityFeedItemRepository->findByPlayer($trackedPlayer),
                ));
                $activityFeed = $activityFeed ? $trackedActivityFeed->merge($activityFeed) : $trackedActivityFeed;
            }
        }

        // Correct passed name of player with obtained display name.
        if ($displayName) {
            $player = new Player($displayName);
        }

        return new LookupResult(
            $player,
            $oldSchool,
            $highScore,
            $activityFeed,
            $trackedPlayer,
            $trainedToday,
            $trainedYesterday,
            $trainedWeek,
            $records,
        );
    }

    public function trackPlayer(Player $player): TrackedPlayer
    {
        if ($this->isReadonly()) {
            throw new \LogicException('Players can\'t be tracked in readonly mode.');
        }

        $trackedPlayer = $this->trackedPlayerRepository->findByName($player->getName());
        if ($trackedPlayer?->isActive()) {
            throw new \InvalidArgumentException(sprintf('Player "%s" is already being tracked.', $player->getName()));
        }

        if (!$trackedPlayer) {
            $trackedPlayer = new TrackedPlayer($player->getName());
            $this->entityManager->persist($trackedPlayer);
        }

        $trackedPlayer->setActive(true);
        $this->entityManager->flush();

        return $trackedPlayer;
    }

    /**
     * Fetches and inserts high scores at current update time. Fixes player name. Updates personal records and returns
     * trained since yesterday when available.
     *
     * @throws FetchFailedException
     */
    public function updateTrackedHighScores(TrackedPlayer $player, int $maxTries = 3): UpdateResult
    {
        if ($this->isReadonly()) {
            throw new \LogicException('Tracked high score can\'t be updated in readonly mode.');
        }

        $updateTime = $this->timeKeeper->getUpdateTime();

        // Check if high scores already exist. Player may have been tracked very recently or the update process was
        // restarted. Return trained for record calculation.
        $rs3HighScore = $this->trackedHighScoreRepository->findByDate(
            $updateTime,
            $player,
            oldSchool: false
        )?->getHighScore();
        $osrsHighScore = $this->trackedHighScoreRepository->findByDate(
            $updateTime,
            $player,
            oldSchool: true
        )?->getHighScore();
        if ($rs3HighScore || $osrsHighScore) {
            $previousRs3HighScore = $this->trackedHighScoreRepository->findByDate(
                $this->timeKeeper->getUpdateTime(-1),
                $player,
                oldSchool: false
            )?->getHighScore();
            $previousOsrsHighScore = $this->trackedHighScoreRepository->findByDate(
                $this->timeKeeper->getUpdateTime(-1),
                $player,
                oldSchool: true
            )?->getHighScore();

            return new UpdateResult(
                $player,
                trainedRs3: $previousRs3HighScore ? $rs3HighScore?->compareTo($previousRs3HighScore) : null,
                trainedOsrs: $previousOsrsHighScore ? $osrsHighScore?->compareTo($previousOsrsHighScore) : null,
            );
        }

        /** @var Rs3HighScore|null $rs3HighScore */
        $rs3HighScore = null;
        /** @var RuneMetricsData|null $runeMetrics */
        $runeMetrics = null;
        for ($i = 0; !$rs3HighScore && $i < $maxTries; $i++) {
            try {
                $rs3HighScore = $this->playerDataFetcher->fetchIndexLite($player->getPlayer(), oldSchool: false);
            } catch (FetchFailedException) {
            }
        }
        for ($i = 0; !$rs3HighScore && $i < $maxTries; $i++) {
            try {
                $runeMetrics = $this->playerDataFetcher->fetchRuneMetrics($player->getPlayer());
                $rs3HighScore = $runeMetrics->highScore;
                break;
            } catch (FetchFailedException) {
            }
        }

        /** @var OsrsHighScore|null $osrsHighScore */
        $osrsHighScore = null;
        for ($i = 0; !$osrsHighScore && $i < $maxTries; $i++) {
            try {
                $osrsHighScore = $this->playerDataFetcher->fetchIndexLite($player->getPlayer(), oldSchool: true);
            } catch (FetchFailedException) {
            }
        }

        if (!$rs3HighScore && !$osrsHighScore) {
            throw new FetchFailedException(sprintf(
                'Failed to obtain any highscores for "%s" after %s tries.',
                $player->getName(),
                $maxTries
            ));
        }

        if ($rs3HighScore) {
            $trackedHighScore = new TrackedHighScore(
                player: $player,
                date: $updateTime,
                oldSchool: false,
                highScore: $rs3HighScore,
            );
            $this->entityManager->persist($trackedHighScore);
        }

        if ($osrsHighScore) {
            $trackedHighScore = new TrackedHighScore(
                player: $player,
                date: $updateTime,
                oldSchool: true,
                highScore: $osrsHighScore,
            );
            $this->entityManager->persist($trackedHighScore);
        }

        // Fix player name.
        if ($runeMetrics && $runeMetrics->displayName !== $player->getName()) {
            $player->setName($runeMetrics->displayName);
        }

        // Update records.
        $trainedRs3 = $rs3HighScore ? $this->updateRecords($player, $rs3HighScore, oldSchool: false) : null;
        $trainedOsrs = $osrsHighScore ? $this->updateRecords($player, $osrsHighScore, oldSchool: true) : null;

        $this->entityManager->flush();

        return new UpdateResult($player, $trainedRs3, $trainedOsrs);
    }

    private function updateRecords(
        TrackedPlayer $player,
        HighScore $currentHighScore,
        bool $oldSchool
    ): ?HighScoreComparison {
        $recordDate = $this->timeKeeper->getRecordDate();
        $previousHighScore = $this->trackedHighScoreRepository->findByDate(
            $this->timeKeeper->getUpdateTime(-1),
            $player,
            $oldSchool,
        )?->getHighScore();
        if (!$previousHighScore) {
            return null;
        }

        $trained = $currentHighScore->compareTo($previousHighScore);
        $records = $this->personalRecordRepository->findRecords($player, $oldSchool);

        foreach ($currentHighScore->getSkills() as $highScoreSkill) {
            /** @var PersonalRecord|null $record */
            $record = $records->get($highScoreSkill->skill);
            $score = $trained->getXpDifference($highScoreSkill->skill);

            if ($score && $score > ($record?->getScore() ?? 0)) {
                if ($record) {
                    $record->updateScore($score, $recordDate);
                } else {
                    $record = new PersonalRecord($player, $highScoreSkill->skill, $score, $recordDate);
                    $this->entityManager->persist($record);
                }
            }
        }

        foreach ($currentHighScore->getActivities() as $highScoreActivity) {
            /** @var PersonalRecord|null $record */
            $record = $records->get($highScoreActivity->activity);
            $score = $trained->getScoreDifference($highScoreActivity->activity);

            if ($score && $score > ($record?->getScore() ?? 0)) {
                if ($record) {
                    $record->updateScore($score, $recordDate);
                } else {
                    $record = new PersonalRecord($player, $highScoreActivity->activity, $score, $recordDate);
                    $this->entityManager->persist($record);
                }
            }
        }

        return $trained;
    }

    /**
     * @return TrackedActivityFeedItem[]
     * @throws FetchFailedException
     */
    public function updateTrackedActivityFeed(TrackedPlayer $player): array
    {
        if ($this->isReadonly()) {
            throw new \LogicException('Tracked activity feed can\'t be updated in readonly mode.');
        }

        $liveFeed = $this->playerDataFetcher->fetchRuneMetrics($player->getPlayer())->activityFeed;
        $latestTrackedItem = $this->trackedActivityFeedItemRepository->findLast($player);

        // Obtain and persist all newly discovered activity feed items
        if ($latestTrackedItem) {
            $newItems = $liveFeed->getItemsAfter($latestTrackedItem->getItem());
            $nextSequenceNumber = $latestTrackedItem->getSequenceNumber() + 1;
        } else {
            $newItems = $liveFeed->items;
            $nextSequenceNumber = 0;
        }

        $newTrackedItems = [];
        foreach (array_reverse($newItems) as $newItem) {
            $newTrackedItem = new TrackedActivityFeedItem($newItem, $player, $nextSequenceNumber++);
            $this->entityManager->persist($newTrackedItem);
            $newTrackedItems[] = $newTrackedItem;
        }

        $this->entityManager->flush();

        return $newTrackedItems;
    }

    public function isReadonly(): bool
    {
        return $this->readonly;
    }
}
