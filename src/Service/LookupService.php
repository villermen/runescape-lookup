<?php

namespace App\Service;

use App\Entity\TrackedHighScore;
use App\Entity\TrackedPlayer;
use App\Model\LookupResult;
use App\Model\Records;
use App\Repository\PersonalRecordRepository;
use App\Repository\TrackedHighScoreRepository;
use App\Repository\TrackedPlayerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Villermen\RuneScape\Exception\FetchFailedException;
use Villermen\RuneScape\Player;
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
        #[Autowire(param: 'app.readonly')]
        private readonly bool $readonly,
    ) {
    }

    public function lookUpPlayer(Player $player, bool $oldSchool): ?LookupResult
    {
        $highScore = null;
        $activityFeed = null;

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
            $highScoreToday = $this->trackedHighScoreRepository->findByDate($this->timeKeeper->getUpdateTime(), $trackedPlayer, $oldSchool)?->getHighScore();
            $highScoreYesterday = $this->trackedHighScoreRepository->findByDate($this->timeKeeper->getUpdateTime(-1), $trackedPlayer, $oldSchool)?->getHighScore();
            $highScoreWeek = $this->trackedHighScoreRepository->findByDate($this->timeKeeper->getUpdateTime(-7), $trackedPlayer, $oldSchool)?->getHighScore();
            $trainedToday = $highScoreToday ? $highScore->compareTo($highScoreToday) : null;
            $trainedYesterday = $highScoreYesterday ? $highScoreToday?->compareTo($highScoreYesterday) : null;
            $trainedWeek = $highScoreWeek ? $highScoreToday?->compareTo($highScoreWeek) : null;
            $records = $this->personalRecordRepository->findRecords($trackedPlayer, $oldSchool);
        }

        // TODO: Get tracked activity feed and merge with live
        // $activityFeed = $this->entityManager->getRepository(TrackedActivityFeedItem::class)->findByPlayer($player, true);

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
     * @throws FetchFailedException
     */
    public function updateTrackedHighScores(TrackedPlayer $trackedPlayer, int $maxTries = 3): void
    {
        if ($this->isReadonly()) {
            throw new \LogicException('Tracked high score can\'t be updated in readonly mode.');
        }

        $updateTime = $this->timeKeeper->getUpdateTime();
        $player = new Player($trackedPlayer->getName());

        $rs3HighScore = null;
        for ($i = 0; !$rs3HighScore && $i < $maxTries; $i++) {
            try {
                $rs3HighScore = $this->playerDataFetcher->fetchIndexLite($player, oldSchool: false);
            } catch (FetchFailedException) {
            }
        }
        for ($i = 0; !$rs3HighScore && $i < $maxTries; $i++) {
            try {
                $rs3HighScore = $this->playerDataFetcher->fetchRuneMetrics($player)->highScore;
                break;
            } catch (FetchFailedException) {
            }
        }

        // OSRS
        $osrsHighScore = null;
        for ($i = 0; !$osrsHighScore && $i < $maxTries; $i++) {
            try {
                $osrsHighScore = $this->playerDataFetcher->fetchIndexLite($player, oldSchool: true);
            } catch (FetchFailedException) {
            }
        }

        if (!$rs3HighScore && !$osrsHighScore) {
            throw new FetchFailedException(sprintf('Failed to obtain any highscores for "%s" after %s tries.', $trackedPlayer->getName(), $maxTries));
        }

        if ($rs3HighScore) {
            $trackedHighScore = new TrackedHighScore(
                player: $trackedPlayer,
                date: $updateTime,
                oldSchool: false,
                highScore: $rs3HighScore,
            );
            $this->entityManager->persist($trackedHighScore);
        }

        if ($osrsHighScore) {
            $trackedHighScore = new TrackedHighScore(
                player: $trackedPlayer,
                date: $updateTime,
                oldSchool: true,
                highScore: $osrsHighScore,
            );
            $this->entityManager->persist($trackedHighScore);
        }

        $this->entityManager->flush();
    }

    public function updateTrackedActivityFeed(TrackedPlayer $trackedPlayer): void
    {
        if ($this->isReadonly()) {
            throw new \LogicException('Tracked activity feed can\'t be updated in readonly mode.');
        }

        // TODO
    }

    public function isReadonly(): bool
    {
        return $this->readonly;
    }
}
