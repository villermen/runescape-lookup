<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UpdatePlayersCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName("app:update:players")
            ->setDescription("Adds current high scores for all tracked and active players to the database.");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // TODO: Implement
    }
}
