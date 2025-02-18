<?php

namespace App\Command;

use App\Entity\DailyRecord;
use App\Entity\TrackedPlayer;
use App\Model\UpdateResult;
use App\Repository\DailyRecordRepository;
use App\Repository\TrackedPlayerRepository;
use App\Service\LookupService;
use App\Service\TimeKeeper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Villermen\RuneScape\Exception\FetchFailedException;
use Villermen\RuneScape\HighScore\ActivityInterface;
use Villermen\RuneScape\HighScore\OsrsActivity;
use Villermen\RuneScape\HighScore\OsrsSkill;
use Villermen\RuneScape\HighScore\Rs3Activity;
use Villermen\RuneScape\HighScore\Rs3Skill;
use Villermen\RuneScape\HighScore\SkillInterface;

class UpdateHighScoresCommand extends Command
{
    use LockableTrait;

    private const MAX_TRIES = 2;
    private const MAX_RETRIES = 2;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TimeKeeper $timeKeeper,
        private readonly TrackedPlayerRepository $trackedPlayerRepository,
        private readonly LookupService $lookupService,
        private readonly DailyRecordRepository $dailyRecordRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('app:update-high-scores');
        $this->setDescription('Adds current high scores for all tracked and active players to the database.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->lock()) {
            $output->writeln('<error>Command is already running in another process!</error>');
            return 1;
        }

        // Prevent multiple runs in a day.
        $recordDate = $this->timeKeeper->getRecordDate();
        if ($this->dailyRecordRepository->hasAnyAtDate($recordDate)) {
            $output->writeln('<error>There already exist records for today!</error>');
            return 1;
        }

        /** @var array<array{SkillInterface|ActivityInterface, DailyRecord}> $dailyRecords */
        $dailyRecords = [];
        $processDailyRecords = function (UpdateResult $updateResult) use (&$dailyRecords, $recordDate): void {
            $entries = [
                ...Rs3Skill::cases(),
                ...Rs3Activity::cases(),
                ...OsrsSkill::cases(),
                ...OsrsActivity::cases(),
            ];

            foreach ($entries as $entry) {
                if ($entry instanceof Rs3Skill) {
                    $score = $updateResult->trainedRs3?->getXpDifference($entry);
                } elseif ($entry instanceof Rs3Activity) {
                    $score = $updateResult->trainedRs3?->getScoreDifference($entry);
                } elseif ($entry instanceof OsrsSkill) {
                    $score = $updateResult->trainedOsrs?->getXpDifference($entry);
                } elseif ($entry instanceof OsrsActivity) { // @phpstan-ignore instanceof.alwaysTrue
                    $score = $updateResult->trainedOsrs?->getScoreDifference($entry);
                } else {
                    $score = null;
                }

                if ($score > 0) {
                    // PHP 8.4: array_find()
                    /** @var DailyRecord|null $dailyRecord */
                    $dailyRecord = null;
                    foreach ($dailyRecords as [$recordEntry, $record]) {
                        if ($recordEntry === $entry) {
                            $dailyRecord = $record;
                            break;
                        }
                    }

                    if (!$dailyRecord) {
                        $dailyRecord = new DailyRecord($updateResult->player, $entry, $score, $recordDate);
                        $dailyRecords[] = [$entry, $dailyRecord];
                    } elseif ($score > $dailyRecord->getScore()) {
                        $dailyRecord->updateScore($score, $updateResult->player);
                    }
                }
            }
        };

        $players = $this->trackedPlayerRepository->findActive();
        /** @var TrackedPlayer[] $failedPlayers */
        $failedPlayers = [];

        $output->writeln(sprintf('Starting high score update for %s players...', count($players)));
        foreach ($players as $player) {
            try {
                $processDailyRecords($this->lookupService->updateTrackedHighScores($player, self::MAX_TRIES));
                $output->writeln(sprintf('Updated high scores for %s.', $player->getName()));
            } catch (FetchFailedException) {
                $failedPlayers[] = $player;
            }
        }

        if (count($failedPlayers) === count($players)) {
            $output->writeln('<error>Failed to update high scores for every tracked player!</error>');
            return 1;
        }

        // Retry all failed players a few more times. Mitigates temporary outages.
        foreach ($failedPlayers as $player) {
            try {
                $processDailyRecords($this->lookupService->updateTrackedHighScores($player, self::MAX_RETRIES));
                $output->writeln(sprintf('Updated high scores for %s after initial failure.', $player->getName()));
            } catch (FetchFailedException) {
                // Deactivate player. Flushed as side effect.
                $player->setActive(false);
                $output->writeln(sprintf('Failed to update high scores for %s again. Deactivating...', $player->getName()));
            }
        }

        $output->writeln(sprintf('Updating %s daily records...', count($dailyRecords)));
        foreach ($dailyRecords as [$entry, $dailyRecord]) {
            $this->entityManager->persist($dailyRecord);
        }
        $this->entityManager->flush();

        $output->writeln('<info>Successfully updated high scores.</info>');
        return 0;
    }
}
