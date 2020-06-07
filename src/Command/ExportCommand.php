<?php

namespace App\Command;

use App\Entity\DailyRecord;
use App\Entity\PersonalRecord;
use App\Entity\TrackedHighScore;
use App\Entity\TrackedPlayer;
use App\Service\TimeKeeper;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Villermen\RuneScape\Exception\FetchFailedException;
use Villermen\RuneScape\Skill;

class ExportCommand extends Command
{
    /** @var EntityManagerInterface */
    protected $entityManager;

    /** @var TimeKeeper */
    protected $timeKeeper;

    /** @var DailyRecord[][] [bool oldSchool][int skillId] */
    protected $dailyRecords = [false => [], true => []];

    public function __construct(EntityManagerInterface $entityManager, TimeKeeper $timeKeeper)
    {
        parent::__construct();

        $this->entityManager = $entityManager;
        $this->timeKeeper = $timeKeeper;
    }

    protected function configure(): void
    {
        $this->setName("app:export");
        $this->addArgument("player", InputArgument::REQUIRED, "Name of the player to export");
        $this->setDescription("Exports a player's historic stats in CSV format.");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument("player");

        $player = $this->entityManager->getRepository(TrackedPlayer::class)->findByName($name);
        if (!$player) {
            $output->writeln(sprintf("<error>Player %s is not being tracked!</error>", $name));
            return 1;
        }

        $highScores = $this->entityManager->getRepository(TrackedHighScore::class)->findBy([
            "player" => $player,
            "oldSchool" => false,
        ], [
            "date" => "ASC",
        ]);

        $output = fopen("php://output", "a");

        $headers = [
            "Date",
        ];

        foreach (Skill::getSkills() as $skill) {
            $headers[] = $skill->getName();
        }

        fputcsv($output, $headers);

        foreach ($highScores as $highScore) {
            $line = [
                $highScore->getDate()->format('Y-m-d'),
            ];
            foreach (Skill::getSkills() as $skill) {
                $highScoreSkill = $highScore->getSkill($skill->getId());
                $line[] = ($highScoreSkill ? $highScoreSkill->getXp() : "0");
            }

            fputcsv($output, $line);
        }

        return 0;
    }

    protected function updatePlayer(TrackedPlayer $player, bool $oldSchool): bool
    {
        try {
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

            return true;
        } catch (FetchFailedException $exception) {
        }

        return false;
    }
}
