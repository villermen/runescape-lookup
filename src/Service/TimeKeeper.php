<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Keeps the time. More interestingly, it thinks it is still yesterday when the update time hasn't been reached yet.
 */
class TimeKeeper
{
    public function __construct(
        #[Autowire(param: 'update_time')] protected readonly string $updateTime
    ) {
    }

    /**
     * Returns today's update time (always in the past).
     *
     * @param int $offsetDays Returned time is offset by the given amount of days.
     */
    public function getUpdateTime(int $offsetDays = 0): \DateTimeImmutable
    {
        $updateTime = new \DateTimeImmutable($this->updateTime);

        if (new \DateTimeImmutable('now') < $updateTime) {
            $updateTime = $updateTime->modify('-1 day');
        }

        if ($offsetDays > 0) {
            $updateTime = $updateTime->modify(sprintf('-%s days', $offsetDays));
        }

        return $updateTime;
    }
}
