<?php

namespace App\Command;

use App\Repository\TrackedPlayerRepository;
use App\Service\LookupService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Villermen\RuneScape\Exception\FetchFailedException;

class UpdateActivityFeedsCommand extends Command
{
    use LockableTrait;

    public function __construct(
        private readonly LookupService $lookupService,
        private readonly TrackedPlayerRepository $trackedPlayerRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('app:update-activity-feeds');
        $this->setDescription('Add new activities for all tracked and active players to the database.');
        $this->addOption('player', null, InputOption::VALUE_REQUIRED, 'Update only a single player with the given name.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->lock()) {
            $output->writeln('<error>Command is already running in another process.</error>');

            return 1;
        }

        if (!$input->getOption('player')) {
            $players = $this->trackedPlayerRepository->findActive();
        } else {
            $player = $this->trackedPlayerRepository->findByName($input->getOption('player'));
            if (!$player) {
                throw new \InvalidArgumentException('Player is not being tracked.');
            }

            $players = [$player];
        }

        foreach ($players as $player) {
            try {
                $newItems = $this->lookupService->updateTrackedActivityFeed($player);
                $output->writeln(sprintf('Updated activity feed for %s with %d new items.', $player->getName(), count($newItems)));
            } catch (FetchFailedException $exception) {
                // Don't handle failed as this update should happen quite frequently and is easily correctable
                $output->writeln(sprintf('<error>Could not update activity feed for %s: %s</error>', $player->getName(), $exception->getMessage()));
            }
        }

        $output->writeln('<info>Successfully updated activity feeds.</info>');
        return 0;
    }
}
