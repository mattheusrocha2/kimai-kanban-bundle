<?php

namespace KimaiPlugin\KanbanBundle\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class KanbanExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('kanban_duration', [$this, 'formatDuration']),
            new TwigFilter('kanban_contrast_color', [$this, 'contrastColor']),
        ];
    }

    public function formatDuration(int $seconds): string
    {
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        return sprintf('%02d:%02d', $hours, $minutes);
    }

    /**
     * Given a "#rrggbb" background color, returns "#000000" or "#ffffff",
     * whichever gives better text contrast (relative luminance heuristic).
     */
    public function contrastColor(?string $hex): string
    {
        if ($hex === null || !preg_match('/^#([0-9a-fA-F]{6})$/', $hex, $m)) {
            return '#000000';
        }

        $r = hexdec(substr($m[1], 0, 2));
        $g = hexdec(substr($m[1], 2, 2));
        $b = hexdec(substr($m[1], 4, 2));

        // Perceived brightness (YIQ formula).
        $brightness = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;

        return $brightness >= 150 ? '#000000' : '#ffffff';
    }
}
