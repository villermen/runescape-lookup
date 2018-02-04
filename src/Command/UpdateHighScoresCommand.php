<?php

namespace App\Command;

use App\Entity\TrackedHighScore;
use App\Entity\TrackedPlayer;
use App\Service\TimeKeeper;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Villermen\RuneScape\Exception\FetchFailedException;

class UpdateHighScoresCommand extends Command
{
    use LockableTrait;

    const RETRY_COUNT = 2;

    /** @var EntityManagerInterface */
    protected $entityManager;

    /** @var TimeKeeper */
    protected $timeKeeper;

    public function __construct(?string $name = null, EntityManagerInterface $entityManager, TimeKeeper $timeKeeper)
    {
        parent::__construct($name);

        $this->entityManager = $entityManager;
        $this->timeKeeper = $timeKeeper;
    }

    protected function configure()
    {
        $this
            ->setName("app:update-high-scores")
            ->setDescription("Adds current high scores for all tracked and active players to the database.");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->lock()) {
            $output->writeln("<error>Command is already running in another process.</error>");

            return 1;
        }

        $players = $this->entityManager->getRepository(TrackedPlayer::class)->findActive();

        if ($this->entityManager->getRepository(TrackedHighScore::class)->findOneBy([
            "date" => $this->timeKeeper->getUpdateTime(0)->modify("midnight")
        ])) {
            throw new Exception("There already exist high scores for today.");
        }

        /** @var TrackedPlayer[] $failedPlayers */
        $failedPlayers = [];
        /** @var TrackedPlayer[] $failedOldSchoolPlayers */
        $failedOldSchoolPlayers = [];

        foreach($players as $player) {
            if ($this->updatePlayer($player, false)) {
                $output->writeln(sprintf("Updated high score for %s.", $player->getName()));
            } else {
                $failedPlayers[] = $player;
            }

            if ($this->updatePlayer($player, true)) {
                $output->writeln(sprintf("Updated old school high score for %s.", $player->getName()));
            } else {
                $failedOldSchoolPlayers[] = $player;
            }
        }

        // Retry failed players
        for ($i = 0; $i < self::RETRY_COUNT; $i++) {
            foreach($failedPlayers as $key => $player) {
                if ($this->updatePlayer($player, false)) {
                    unset($failedPlayers[$key]);

                    $output->writeln(sprintf("Updated high score for %s after %d retries.", $player->getName(), $i + 1));
                }
            }

            foreach($failedOldSchoolPlayers as $key => $player) {
                if ($this->updatePlayer($player, true)) {
                    unset($failedOldSchoolPlayers[$key]);

                    $output->writeln(sprintf("Updated old school high score for %s after %d retries.", $player->getName(), $i + 1));
                }
            }
        }

        // Mark players failed in both high scores as inactive, unless all players failed
        if (count($failedPlayers) + count($failedOldSchoolPlayers) < count($players) * 2) {
            foreach($failedPlayers as $player) {
                if (in_array($player, $failedOldSchoolPlayers, true)) {
                    $player->setActive(false);

                    $output->writeln(sprintf("<error>Failed to update any high score for %s, deactivating...</error>", $player->getName()));
                }
            }
        } else {
            $output->writeln("<error>Failed to update high scores for every tracked player!</error>");

            return 1;
        }

        $this->entityManager->flush();

        $output->writeln("<info>Successfully updated high scores.</info>");

        return 0;
    }

    protected function updatePlayer(TrackedPlayer $player, bool $oldSchool): bool
    {
        try {
            $highScore = $oldSchool ? $player->getOldSchoolSkillHighScore() : $player->getSkillHighScore();

            // Fix name if readily available
            if ($player->getDataFetcher()->getCachedRealName($player->getName())) {
                $player->fixName();
            }

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
                            $newRecord = new PersonalRecord($player, $skillComparison->getSkill(), $skillComparison->getXpDifference(), $oldSchool);
                            $this->entityManager->persist($newRecord);
                        }
                    }
                }
            }

            return true;
        } catch (FetchFailedException $exception) {
        }

        return false;
    }
}
