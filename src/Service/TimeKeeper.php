<?php

namespace App\Service;

use DateTime;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Keeps the time.
 * More interestingly, it thinks it is still yesterday when the update time hasn't been reached yet.
 */
class TimeKeeper
{
    /** @var DateTime */
    protected $updateTime;

    public function __construct(ContainerInterface $container)
    {
        $updateTime = new DateTime($container->getParameter("update_time"));

        if (new DateTime() < $this->updateTime) {
            $updateTime->modify("-1 day");
        }

        $this->updateTime = $updateTime;
    }

    /**
     * Returns today's update time (always in the past).
     *
     * @param int $offsetDays Returned time is offset by the given amount of days.
     * @return DateTime
     */
    public function getUpdateTime(int $offsetDays = 0): DateTime
    {
        return (clone $this->updateTime)->modify(sprintf("%d days", $offsetDays));
    }
}
