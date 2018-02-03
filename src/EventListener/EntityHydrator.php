<?php


namespace App\EventListener;

use App\Entity\TrackedPlayer;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Villermen\RuneScape\PlayerDataFetcher;

class EntityHydrator
{
    /** @var PlayerDataFetcher */
    protected $dataFetcher;

    public function __construct(PlayerDataFetcher $dataFetcher)
    {
        $this->dataFetcher = $dataFetcher;
    }

    public function postLoad(LifecycleEventArgs $eventArgs)
    {
        $entity = $eventArgs->getEntity();
        if ($entity instanceof TrackedPlayer) {
            $entity->setDataFetcher($this->dataFetcher);
        }
    }
}
