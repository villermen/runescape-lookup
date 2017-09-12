<?php

namespace RuneScapeLookupBundle\Service;

use DateTime;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Keeps the date.
 * More interestingly, it thinks it is still yesterday when the update time hasn't been reached yet.
 */
class DateKeeper
{
    /** @var DateTime */
    protected $updateTime;

    public function __construct(ContainerInterface $container)
    {
        $this->updateTime = new DateTime($container->getParameter("update_time"));
    }

    /**
     * Returns the update date of a relative day based on the current time.
     * The UpDate if you will.
     * Passing 0 means today, -1 yesterday, 1 tomorrow, etc.
     *
     * @param int $offsetDays
     * @return DateTime
     */
    public function getUpdateDate(int $offsetDays)
    {
        $currentTime = new DateTime();

        if ($currentTime < $this->updateTime) {
            $offsetDays--;
        }

        return new DateTime(sprintf("-%s days midnight", $offsetDays));
    }
}
