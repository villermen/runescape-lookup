<?php

namespace App\Controller;

use App\Entity\TrackedHighScore;
use App\Entity\TrackedPlayer;
use App\Model\LookupResult;
use App\Repository\DailyRecordRepository;
use App\Repository\TrackedPlayerRepository;
use App\Service\LookupService;
use App\Service\TimeKeeper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Villermen\RuneScape\Exception\FetchFailedException;
use Villermen\RuneScape\Exception\InvalidNameException;
use Villermen\RuneScape\HighScore\ActivityInterface;
use Villermen\RuneScape\HighScore\HighScoreActivity;
use Villermen\RuneScape\HighScore\OsrsSkill;
use Villermen\RuneScape\Player;
use Villermen\RuneScape\Service\PlayerDataFetcher;

class LookupController extends AbstractController
{
    public function __construct(
        private readonly DailyRecordRepository $dailyRecordRepository,
        private readonly TimeKeeper $timeKeeper,
        private readonly PlayerDataFetcher $playerDataFetcher,
        private readonly LookupService $lookupService,
    ) {
    }

    #[Route('/', 'lookup')]
    public function indexAction(Request $request): Response
    {
        // Backward-compatible form handling.
        $name1 = $request->query->getString('player1') ?: null;
        $name2 = $request->query->getString('player2') ?: null;
        $oldSchool = $request->query->getBoolean('oldschool');
        $group = $request->query->getString('group') ?: null;
        $game = $oldSchool ? 'osrs' : 'rs3';

        if ($group) {
            return $this->redirectToRoute('lookup_group', [
                'game' => $game,
                'name' => $group,
            ]);
        }

        if ($name1 && $name2) {
            return $this->redirectToRoute('lookup_compare', [
                'game' => $game,
                'name1' => $name1,
                'name2' => $name2,
            ]);
        }

        if ($name1) {
            return $this->redirectToRoute('lookup_player', [
                'game' => $game,
                'name' => $name1,
            ]);
        }

        // Fetch yesterday's records
        $readonly = $this->lookupService->isReadonly();
        $recordTime = $this->timeKeeper->getRecordDate();
        $dailyRecords = $readonly ? [] : $this->dailyRecordRepository->findRecords($recordTime, oldSchool: false);
        $dailyOldSchoolRecords = $readonly ? [] : $this->dailyRecordRepository->findRecords($recordTime, oldSchool: true);
        $nextUpdateTime = $this->timeKeeper->getUpdateTime(1);

        return $this->render('lookup/overview.html.twig', [
            'dailyRecords' => $dailyRecords,
            'dailyOldSchoolRecords' => $dailyOldSchoolRecords,
            'updateTime' => $nextUpdateTime->format('G:i'),
            'timeTillUpdate' => (new \DateTime())->diff($nextUpdateTime)->format('%h:%I'),
            'formValues' => [
                'oldSchool' => $oldSchool,
                'name1' => $name1,
                'name2' => $name2,
            ],
        ]);
    }

    #[Route('/{game<rs3|osrs>}/player/{name}', 'lookup_player')]
    public function playerAction(Request $request, string $game, string $name): Response
    {
        $oldSchool = $game === 'osrs';

        try {
            $player = new Player($name);
        } catch (InvalidNameException $exception) {
            throw new BadRequestException(sprintf('Invalid name "%s".', $exception->name), previous: $exception);
        }


        $lookupResult = $this->lookupService->lookUpPlayer($player, $oldSchool);
        if (!$lookupResult) {
            throw new NotFoundHttpException(sprintf('%s player "%s" could not be found.', strtoupper($game), $name));
        }

        if (
            !$lookupResult->isTracked() &&
            !$this->lookupService->isReadonly() &&
            $request->query->getBoolean('track')
        ) {
            $trackedPlayer = $this->lookupService->trackPlayer($player);

            // Immediately update tracked high scores to start tracking progress immediately.
            $this->lookupService->updateTrackedHighScores($trackedPlayer);

            return $this->redirectToRoute('lookup_player', [
                'game' => $game,
                'name' => $name,
            ]);
        }

        return $this->render('lookup/player.html.twig', [
            'lookup' => $lookupResult,
        ]);
    }

    #[Route('/{game<rs3|osrs>}/compare/{name1}/{name2}', 'lookup_compare')]
    public function compareAction(string $game, string $name1, string $name2): Response
    {
        $oldSchool = $game === 'osrs';

        try {
            $player1 = new Player($name1);
            $player2 = new Player($name2);
        } catch (InvalidNameException $exception) {
            throw new BadRequestException(sprintf('Invalid name "%s".', $exception->name), previous: $exception);
        }

        $lookup1 = $this->lookupService->lookUpPlayer($player1, $oldSchool);
        if (!$lookup1) {
            throw new NotFoundHttpException(sprintf('%s player "%s" could not be found.', strtoupper($game), $name1));
        }
        $lookup2 = $this->lookupService->lookUpPlayer($player2, $oldSchool);
        if (!$lookup2) {
            throw new NotFoundHttpException(sprintf('%s player "%s" could not be found.', strtoupper($game), $name2));
        }

        $comparison = $lookup1->highScore->compareTo($lookup2->highScore);

        // array_unique does not support enums =S
        $activities = array_map(
            fn (HighScoreActivity $activity): ActivityInterface => $activity->activity,
            $lookup1->getActivitiesWithScore(),
        );
        foreach ($lookup2->getActivitiesWithScore() as $activity2) {
            if (in_array($activity2->activity, $activities)) {
                continue;
            }

            $activities[] = $activity2->activity;
        }

        return $this->render('lookup/compare.html.twig', [
            'lookup1' => $lookup1,
            'lookup2' => $lookup2,
            'comparison' => $comparison,
            'activities' => $activities,
        ]);
    }

    #[Route('{game<rs3|osrs>}/group/{name}', 'lookup_group')]
    public function groupAction(string $game, string $name): Response
    {
        $oldSchool = $game === 'osrs';

        try {
            $group = $this->playerDataFetcher->fetchGroupIronman($name, $oldSchool);
        } catch (FetchFailedException $exception) {
            throw new NotFoundHttpException(sprintf('%s ironman group "%s" could not be found.', strtoupper($game), $name), $exception);
        }

        return $this->handleMulti($game, $group->players, sprintf('Ironman group: %s', $group->displayName));
    }

    #[Route('{game<rs3|osrs>}/multi', 'lookup_multi')]
    public function multiAction(string $game, #[MapQueryParameter] string $players): Response
    {
        $players = explode(',', $players);

        try {
            $players = array_map(fn (string $name) => new Player($name), $players);
        } catch (InvalidNameException $exception) {
            throw new BadRequestException(sprintf('Invalid name "%s".', $exception->name), previous: $exception);
        }

        return $this->handleMulti($game, $players, 'Multi lookup');
    }

    /**
     * @param non-empty-array<Player> $players
     */
    private function handleMulti(string $game, array $players, string $title): Response
    {
        $oldSchool = $game === 'osrs';

        /** @var LookupResult[] $lookups */
        $lookups = [];
        foreach ($players as $player) {
            $lookup = $this->lookupService->lookUpPlayer($player, $oldSchool);
            if (!$lookup) {
                throw new NotFoundHttpException(sprintf('%s player "%s" could not be found.', strtoupper($game), $player->getName()));
            }

            $lookups[] = $lookup;
        }

        // Order by total level > XP.
        usort($lookups, function (LookupResult $lookup1, LookupResult $lookup2): int {
            $total1 = $lookup1->highScore->getSkill(OsrsSkill::TOTAL);
            $total2 = $lookup2->highScore->getSkill(OsrsSkill::TOTAL);
            return $total1->level !== $total2->level
                ? $total2->level <=> $total1->level
                : $total2->xp <=> $total1->xp;
        });

        $skills = [];
        foreach (reset($lookups)->highScore->getSkills() as $highScoreSkill) {
            $skill = $highScoreSkill->skill;
            $highestXp = 0;
            foreach ($lookups as $lookup) {
                $highestXp = max($highestXp, $lookup->highScore->getSkill($skill)->xp ?? 0);
            }

            $skills[] = [
                'skill' => $skill,
                'highestXp' => $highestXp,
            ];
        }

        $activities = [];
        foreach (reset($lookups)->highScore->getActivities() as $highScoreActivity) {
            $activity = $highScoreActivity->activity;
            $highestScore = 0;
            foreach ($lookups as $lookup) {
                $highestScore = max($highestScore, $lookup->highScore->getActivity($activity)->score ?? 0);
            }

            // Only includes activities for which one of the group members is ranked.
            if ($highestScore > 0) {
                $activities[] = [
                    'activity' => $activity,
                    'highestScore' => $highestScore,
                ];
            }
        }

        return $this->render('lookup/multi.html.twig', [
            'title' => $title,
            'lookups' => $lookups,
            'skills' => $skills,
            'activities' => $activities,
        ]);
    }
}
