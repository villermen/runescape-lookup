<?php

namespace App\Command;

use App\Entity\TrackedActivityFeedItem;
use App\Entity\TrackedPlayer;
use App\Service\TimeKeeper;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Villermen\RuneScape\ActivityFeed\ActivityFeedItem;
use Villermen\RuneScape\Exception\FetchFailedException;

class UpdateActivityFeedsCommand extends Command
{
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
            ->setName("app:update-activity-feeds")
            ->setDescription("Add new activities for all tracked and active players to the database.")
            ->addOption("player", null, InputOption::VALUE_REQUIRED, "Update only a single player with the given name.");
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // TODO: Lock

        $playerRepository = $this->entityManager->getRepository(TrackedPlayer::class);

        if (!$input->getOption("player")) {
            $players = $playerRepository->findActive();
        } else {
            $player = $playerRepository->findOneBy(["name" => $input->getOption("player")]);

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
                $output->writeln(sprintf("Could not update activity feed for %s: <error>%s</error>", $player->getName(), $exception->getMessage()));
            }
        }
    }
}
