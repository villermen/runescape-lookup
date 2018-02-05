<?php

namespace App\Service;

use Twig_Extension;
use Twig_SimpleFilter;

class Formatter extends Twig_Extension
{
    public function getFilters()
    {
        return [
            new Twig_SimpleFilter("red_to_green", [$this, "redToGreen"]),
            new Twig_SimpleFilter("format_difference", [$this, "formatDifference"])
        ];
    }

    /**
     * Converts a value into a hex color scaled by the bounds supplied by $redValue and $greenValue.
     *
     * @param float $value
     * @param float $redBound
     * @param float $greenBound
     * @return string
     */
    public function redToGreen(float $value, float $redBound = 0, float $greenBound = 1): string
    {
        $fraction = ($value - $redBound) / ($greenBound - $redBound);
        $fraction = max(0, min(1, $fraction));

        $redComponent = 255;
        $greenComponent = 255;

        if ($fraction <= 0.5) {
            $greenComponent = round($fraction * 510);
        } else {
            $redComponent = 510 - round($fraction * 510);
        }

        return sprintf("#%06X", $redComponent * 256 * 256 + $greenComponent * 256);
    }

    /**
     * @param float $difference
     * @return string
     */
    public function formatDifference(float $difference): string
    {
        if (!$difference) {
            return "=";
        }

        return ($difference > 0 ? "+" : "") . number_format($difference);
    }
}
