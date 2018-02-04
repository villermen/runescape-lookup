<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateHighScoresCommand extends Command
{
    use LockableTrait;

    const RETRY_COUNT = 2;

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

        // TODO: Implement

        // TODO: handle failedPlayers after the others
        // TODO: if all failed, don't set players as inactive because it's probably the API acting up
        // TODO: Update name if it happens to be available (PlayerDataFetcher.has(), or isAvailable())

        return 1;
    }
}
