<?php

namespace App\Command;

use App\Entity\DailyRecord;
use App\Entity\PersonalRecord;
use App\Entity\TrackedHighScore;
use App\Entity\TrackedPlayer;
use App\Service\TimeKeeper;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bridge\Monolog\Formatter\ConsoleFormatter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Villermen\RuneScape\Exception\FetchFailedException;

class UpdateHighScoresCommand extends Command
{
    use LockableTrait;

    private const RETRY_COUNT = 5;

    private EntityManagerInterface $entityManager;

    private TimeKeeper $timeKeeper;

    /** @var DailyRecord[][] [bool oldSchool][int skillId] */
    private array $dailyRecords = [false => [], true => []];

    public function __construct(EntityManagerInterface $entityManager, TimeKeeper $timeKeeper)
    {
        parent::__construct();

        $this->entityManager = $entityManager;
        $this->timeKeeper = $timeKeeper;
    }

    protected function configure(): void
    {
        $this->setName("app:update-high-scores");
        $this->setDescription("Adds current high scores for all tracked and active players to the database.");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $writeln = function (string $message) use ($output): void {
            $output->writeln(sprintf('[%s] %s', date('H:i'), $message));
        };

        try {
            if (!$this->lock()) {
                $writeln("<error>Command is already running in another process.</error>");
                return 1;
            }

            /** @var TrackedPlayer[] $players */
            $players = $this->entityManager->getRepository(TrackedPlayer::class)->findActive();

            if ($this->entityManager->getRepository(TrackedHighScore::class)->findOneBy([
                "date" => $this->timeKeeper->getUpdateTime(0)->modify("midnight")
            ])) {
                $writeln("<error>There already exist high scores for today.</error>");
                return 1;
            }

            /** @var TrackedPlayer[] $failedPlayers */
            $failedPlayers = [];
            /** @var TrackedPlayer[] $failedOldSchoolPlayers */
            $failedOldSchoolPlayers = [];

            $writeln(sprintf('Starting high score update for %s players...', count($players)));
            foreach ($players as $player) {
                try {
                    $this->updatePlayer($player, oldSchool: false);
                    $writeln(sprintf("Updated high score for %s.", $player->getName()));
                } catch (FetchFailedException) {
                    $failedPlayers[] = $player;
                }

                try {
                    $this->updatePlayer($player, oldSchool: true);
                    $writeln(sprintf("Updated old school high score for %s.", $player->getName()));
                } catch (FetchFailedException) {
                    $failedOldSchoolPlayers[] = $player;
                }
            }

            // Retry failed players
            $writeln(sprintf('Retrying %s failed updates...', count($failedPlayers)));
            for ($i = 0; $i < self::RETRY_COUNT; $i++) {
                foreach ($failedPlayers as $key => $player) {
                    try {
                        $this->updatePlayer($player, oldSchool: false);
                        unset($failedPlayers[$key]);
                        $writeln(sprintf("Updated high score for %s after %d retries.", $player->getName(), $i + 1));
                    } catch (FetchFailedException) {
                    }
                }

                foreach ($failedOldSchoolPlayers as $key => $player) {
                    try {
                        $this->updatePlayer($player, oldSchool: true);
                        unset($failedOldSchoolPlayers[$key]);
                        $writeln(sprintf("Updated old school high score for %s after %d retries.", $player->getName(), $i + 1));
                    } catch (FetchFailedException) {
                    }
                }
            }

            // Mark players failed in both high scores as inactive, unless all players failed
            if (count($failedPlayers) + count($failedOldSchoolPlayers) < count($players) * 2) {
                foreach ($failedPlayers as $player) {
                    if (in_array($player, $failedOldSchoolPlayers, true)) {
                        $player->setActive(false);

                        $writeln(sprintf("<error>Failed to update any high score for %s, deactivating...</error>", $player->getName()));
                    }
                }
            } else {
                $writeln("<error>Failed to update high scores for every tracked player!</error>");

                return 1;
            }

            $this->entityManager->flush();

            // Persist daily records
            $writeln('Updating daily records...');
            foreach ($this->dailyRecords as $dailyRecordArray) {
                foreach ($dailyRecordArray as $dailyRecord) {
                    $this->entityManager->persist($dailyRecord);
                }
            }

            $this->entityManager->flush();

            $writeln("<info>Successfully updated high scores.</info>");

            return 0;
        } catch (\Throwable $exception) {
            $writeln(sprintf('<error>An unexpected error occurred: %s', $exception->getMessage()));
            return 1;
        }
    }

    /**
     * @throws FetchFailedException
     */
    private function updatePlayer(TrackedPlayer $player, bool $oldSchool): void
    {
        $highScore = $oldSchool ? $player->getOldSchoolSkillHighScore() : $player->getSkillHighScore();

        // Fix name if readily available
        $player->fixNameIfCached();

        $trackedHighScore = new TrackedHighScore($highScore->getSkills(), $player, $oldSchool);

        $this->entityManager->persist($trackedHighScore);

        // Create personal records
        $previousHighScore = $this->entityManager->getRepository(TrackedHighScore::class)->findByDate(
            $this->timeKeeper->getUpdateTime(-1), $player, $oldSchool
        );

        if ($previousHighScore) {
            $comparison = $highScore->compareTo($previousHighScore);

            $records = $this->entityManager->getRepository(PersonalRecord::class)->findHighestRecords($player, $oldSchool);

            foreach($comparison->getSkills() as $skillComparison) {
                if ($skillComparison->getXpDifference() > 0) {
                    $skillId = $skillComparison->getSkill()->getId();
                    if (!isset($records[$skillId]) || $skillComparison->getXpDifference() > $records[$skillId]->getXpGain()) {
                        $newRecord = new PersonalRecord(
                            $player, $skillComparison->getSkill(), $skillComparison->getXpDifference(),
                            $oldSchool, $this->timeKeeper->getUpdateTime(-1)
                        );

                        $this->entityManager->persist($newRecord);
                    }

                    // Set in daily records if it is greater
                    if (!isset($this->dailyRecords[$oldSchool][$skillId]) ||
                        $skillComparison->getXpDifference() > $this->dailyRecords[$oldSchool][$skillId]->getXpGain()) {
                        $this->dailyRecords[$oldSchool][$skillId] = new DailyRecord(
                            $player, $skillComparison->getSkill(), $skillComparison->getXpDifference(),
                            $oldSchool, $this->timeKeeper->getUpdateTime(-1)
                        );
                    }
                }
            }
        }
    }
}
