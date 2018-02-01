<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateActivityFeedsCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName("app:update:activity-feeds")
            ->setDescription("Add new activities for all tracked and active players to the database.");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // TODO: Implement
    }
}
