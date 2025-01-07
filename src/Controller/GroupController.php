<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Villermen\RuneScape\Exception\FetchFailedException;
use Villermen\RuneScape\HighScore\OsrsActivity;
use Villermen\RuneScape\HighScore\OsrsHighScore;
use Villermen\RuneScape\HighScore\OsrsSkill;
use Villermen\RuneScape\Service\PlayerDataFetcher;

class GroupController extends AbstractController
{
    public function __construct(
        private readonly PlayerDataFetcher $playerDataFetcher,
    ) {
    }

    #[Route('/group/{groupName}', 'group')]
    public function indexAction(string $groupName): Response
    {
        try {
            $group = $this->playerDataFetcher->fetchGroupIronman($groupName);
        } catch (FetchFailedException) {
            throw new NotFoundHttpException('Ironman group not found.');
        }

        // TODO: Order by total level > xp
        // TODO: Abstract the service into LookupService with HighScore|TrackedHighScore?
        // TODO: Some static typing would be nice...
        // TODO: Guardians of the Rift: Rifts Closed is too long, just Guardians of the Rift suffices.
        // [player, highscore, trackedHighscore?, trained?]
        /** @var array<array{highScore: OsrsHighScore}> $players */
        $players = [];
        foreach ($group->players as $player) {
            $players[] = [
                'name' => $player->getName(),
                'highScore' => $this->playerDataFetcher->fetchIndexLite($player, oldSchool: true),
            ];
        }

        usort($players, fn (array $player1, array $player2) => (
            $player2['highScore']->getSkill(OsrsSkill::TOTAL)->level <=>
            $player1['highScore']->getSkill(OsrsSkill::TOTAL)->level
        ));

        $skills = OsrsSkill::cases();
        $highestLevels = [];
        foreach ($skills as $skill) {
            foreach ($players as ['highScore' => $highScore]) {
                $highestLevels[$skill->getId()] = max($highestLevels[$skill->getId()] ?? 0, $highScore->getSkill($skill)->level);
            }
        }

        // Only include activities for which one of the group members is ranked.
        $activities = array_filter(OsrsActivity::cases(), function (OsrsActivity $activity) use ($players): bool {
            foreach ($players as ['highScore' => $highScore]) {
                $highScoreActivity = $highScore->getActivity($activity);
                if ($highScoreActivity->score) {
                    return true;
                }
            }

            return false;
        });
        $highestScores = [];
        foreach ($activities as $activity) {
            foreach ($players as ['highScore' => $highScore]) {
                $highestScores[$activity->getId()] = max($highestScores[$activity->getId()] ?? 0, $highScore->getActivity($activity)->score);
            }
        }

        return $this->render('group/index.html.twig', [
            'group' => $group,
            'players' => $players,
            'skills' =>  $skills,
            'highestLevels' => $highestLevels,
            'activities' => $activities,
            'highestScores' => $highestScores,
        ]);
    }
}
