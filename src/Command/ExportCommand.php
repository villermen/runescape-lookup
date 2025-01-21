<?php

namespace App\Command;

use App\Entity\TrackedHighScore;
use App\Repository\TrackedHighScoreRepository;
use App\Repository\TrackedPlayerRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Villermen\RuneScape\HighScore\OsrsSkill;
use Villermen\RuneScape\HighScore\Rs3Skill;

class ExportCommand extends Command
{
    public function __construct(
        private readonly TrackedPlayerRepository $trackedPlayerRepository,
        private readonly TrackedHighScoreRepository $trackedHighScoreRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('app:export');
        $this->addArgument('player', InputArgument::REQUIRED, 'Name of the player to export');
        $this->addOption('oldschool', /*mode: InputOption::VALUE_NONE*/);
        $this->setDescription('Exports a player\'s historic stats in CSV format.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('player');
        $oldSchool = $input->getOption('oldschool');

        $player = $this->trackedPlayerRepository->findByName($name);
        if (!$player) {
            $output->writeln(sprintf('<error>Player %s is not being tracked!</error>', $name));
            return 1;
        }

        /** @var TrackedHighScore[] $trackedHighScores */
        $trackedHighScores = $this->trackedHighScoreRepository->findBy([
            'player' => $player,
            'highScore.oldSchool' => $oldSchool,
        ], [
            'date' => 'ASC',
        ]);

        $output = fopen('php://output', 'a');
        if (!$output) {
            throw new \RuntimeException('Failed to open output stream!');
        }

        $headers = [
            'Date',
        ];

        $skills = $oldSchool ? OsrsSkill::cases() : Rs3Skill::cases();
        foreach ($skills as $skill) {
            $headers[] = $skill->getName();
        }

        fputcsv($output, $headers);

        foreach ($trackedHighScores as $trackedHighScore) {
            $line = [
                $trackedHighScore->getDate()->format('Y-m-d'),
            ];

            foreach ($skills as $skill) {
                $line[] = $trackedHighScore->getHighScore()->getSkill($skill)->xp ?? 0;
            }

            fputcsv($output, $line);
        }

        return 0;
    }
}
