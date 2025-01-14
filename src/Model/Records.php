<?php

namespace App\Model;

use App\Entity\AbstractRecord;
use Villermen\RuneScape\HighScore\ActivityInterface;
use Villermen\RuneScape\HighScore\SkillInterface;

/**
 * @template T of AbstractRecord = AbstractRecord
 * @implements \IteratorAggregate<array-key, T>
 */
class Records implements \IteratorAggregate
{
    /**
     * @param array<array-key, T> $records
     */
    public function __construct(
        protected readonly array $records,
    ) {
    }

    /**
     * @template Y of SkillInterface|ActivityInterface
     * @param Y $type
     * @return T<Y>|null
     */
    public function get(SkillInterface|ActivityInterface $type): ?AbstractRecord
    {
        foreach ($this->records as $record) {
            if ($record->getType() === $type) {
                return $record;
            }
        }

        return null;
    }

    /**
     * @return \Traversable<array-key, T>
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->records);
    }
}
