<?php

namespace App\Service;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class Formatter extends AbstractExtension
{
    /**
     * @return TwigFilter[]
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('red_to_green', [$this, 'redToGreen']),
            new TwigFilter('format_difference', [$this, 'formatDifference'])
        ];
    }

    /**
     * Converts a value into a hex color scaled by the bounds supplied by $redValue and $greenValue.
     */
    public function redToGreen(?float $value, float $redBound = 0, float $greenBound = 1): string
    {
        $fraction = (($value ?? 0.0) - $redBound) / ($greenBound - $redBound);
        $fraction = max(0, min(1, $fraction));

        $redComponent = 255;
        $greenComponent = 255;

        if ($fraction <= 0.5) {
            $greenComponent = round($fraction * 510);
        } else {
            $redComponent = 510 - round($fraction * 510);
        }

        return sprintf('#%06X', $redComponent * 256 * 256 + $greenComponent * 256);
    }

    public function formatDifference(?float $difference): string
    {
        if (!$difference) {
            return '=';
        }

        return ($difference > 0 ? '+' : '') . number_format($difference);
    }
}
