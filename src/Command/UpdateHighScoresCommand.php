<?php

namespace App\Command;

use App\Entity\TrackedHighScore;
use App\Entity\TrackedPlayer;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
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

    public function __construct(?string $name = null, EntityManagerInterface $entityManager)
    {
        parent::__construct($name);

        $this->entityManager = $entityManager;
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

        $date = new DateTime("midnight");

        $players = $this->entityManager->getRepository(TrackedPlayer::class)->findActive();

        $failedPlayers = [];
        foreach($players as $player) {
            $this->updatePlayer($player);
        }

        // TODO: Retry failed players

        // TODO: Mark failed players as inactive, unless all players failed

        // TODO: Promote the highest personal records of the day to daily records


        return 1;
    }

    protected function updatePlayer(TrackedPlayer $player): bool
    {
        try {
            $player->getSkillHighScore()

            // TODO: Compare with previous day if possible $this->entityManager->getRepository(TrackedHighScore::class)

            // TODO: Update name if cached
        } catch (FetchFailedException $exception) {
            return false;
        }

        // TODO: $player->getOldSchoolSkillHighScore(), but don't return false when failed (but don't try again either?)
    }
}
