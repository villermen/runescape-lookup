<?php

namespace App\Command;

use App\Entity\TrackedActivityFeedItem;
use App\Entity\TrackedPlayer;
use App\Service\TimeKeeper;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Villermen\RuneScape\ActivityFeed\ActivityFeedItem;
use Villermen\RuneScape\Exception\FetchFailedException;

class UpdateActivityFeedsCommand extends Command
{
    use LockableTrait;

    /** @var EntityManagerInterface */
    protected $entityManager;

    /** @var TimeKeeper */
    protected $timeKeeper;

    public function __construct(EntityManagerInterface $entityManager, TimeKeeper $timeKeeper)
    {
        parent::__construct();

        $this->entityManager = $entityManager;
        $this->timeKeeper = $timeKeeper;
    }

    protected function configure(): void
    {
        $this->setName("app:update-activity-feeds");
        $this->setDescription("Add new activities for all tracked and active players to the database.");
        $this->addOption("player", null, InputOption::VALUE_REQUIRED, "Update only a single player with the given name.");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->lock()) {
            $output->writeln("<error>Command is already running in another process.</error>");

            return 1;
        }

        $playerRepository = $this->entityManager->getRepository(TrackedPlayer::class);

        if (!$input->getOption("player")) {
            $players = $playerRepository->findActive();
        } else {
            $player = $playerRepository->findByName($input->getOption("player"));

            if (!$player) {
                throw new Exception("Player is not being tracked.");
            }

            $players = [$player];
        }

        $activityRepository = $this->entityManager->getRepository(TrackedActivityFeedItem::class);

        foreach($players as $player) {
            try {
                $liveActivityFeed = $player->getActivityFeed();
                $latestTrackedActivity = $activityRepository->findLast($player);

                // Obtain and persist all newly discovered activity feed items
                if ($latestTrackedActivity) {
                    $newActivities = $liveActivityFeed->getItemsAfter($latestTrackedActivity);
                    $nextSequenceNumber = $latestTrackedActivity->getSequenceNumber() + 1;
                } else {
                    $newActivities = $liveActivityFeed->getItems();
                    $nextSequenceNumber = 0;
                }

                /** @var ActivityFeedItem[] $newActivities */
                $newActivities = array_reverse($newActivities);

                foreach($newActivities as $newActivity) {
                    $trackedActivity = new TrackedActivityFeedItem($newActivity, $player, $nextSequenceNumber++);
                    $this->entityManager->persist($trackedActivity);
                }

                $this->entityManager->flush();
                $this->entityManager->clear(TrackedActivityFeedItem::class);

                $output->writeln(sprintf("Updated activity feed for %s with %d new items.", $player->getName(), count($newActivities)));
            } catch (FetchFailedException $exception) {
                // Don't handle failed as this update should happen quite frequently and is easily correctable
                $output->writeln(sprintf("<error>Could not update activity feed for %s: %s</error>", $player->getName(), $exception->getMessage()));
            }
        }

        $output->writeln("<info>Successfully updated activity feeds.</info>");

        return 0;
    }
}
