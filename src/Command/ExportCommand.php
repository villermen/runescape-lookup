<?php

namespace App\Command;

use App\Entity\TrackedHighScore;
use App\Entity\TrackedPlayer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Villermen\RuneScape\Skill;

class ExportCommand extends Command
{
    /** @var EntityManagerInterface */
    protected $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();

        $this->entityManager = $entityManager;
    }

    protected function configure(): void
    {
        $this->setName('app:export');
        $this->addArgument('player', InputArgument::REQUIRED, 'Name of the player to export');
        $this->setDescription('Exports a player\'s historic stats in CSV format.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('player');

        $player = $this->entityManager->getRepository(TrackedPlayer::class)->findByName($name);
        if (!$player) {
            $output->writeln(sprintf('<error>Player %s is not being tracked!</error>', $name));
            return 1;
        }

        $highScores = $this->entityManager->getRepository(TrackedHighScore::class)->findBy([
            'player' => $player,
            'oldSchool' => false,
        ], [
            'date' => 'ASC',
        ]);

        $output = fopen('php://output', 'a');

        $headers = [
            'Date',
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
                $line[] = ($highScoreSkill ? $highScoreSkill->getXp() : '0');
            }

            fputcsv($output, $line);
        }

        return 0;
    }
}
