<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class TimeAgoExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('ago', [$this, 'timeAgo']),
        ];
    }

    public function timeAgo(\DateTimeInterface $datetime): string
    {
        $now = new \DateTime();
        $diff = $now->getTimestamp() - $datetime->getTimestamp();

        if ($diff < 60) {
            return 'just now';
        }

        if ($diff < 3600) {
            $minutes = floor($diff / 60);
            return $minutes . ' ' . ($minutes == 1 ? 'minute' : 'minutes') . ' ago';
        }

        if ($diff < 86400) {
            $hours = floor($diff / 3600);
            return $hours . ' ' . ($hours == 1 ? 'hour' : 'hours') . ' ago';
        }

        if ($diff < 604800) {
            $days = floor($diff / 86400);
            return $days . ' ' . ($days == 1 ? 'day' : 'days') . ' ago';
        }

        if ($diff < 2592000) {
            $weeks = floor($diff / 604800);
            return $weeks . ' ' . ($weeks == 1 ? 'week' : 'weeks') . ' ago';
        }

        if ($diff < 31536000) {
            $months = floor($diff / 2592000);
            return $months . ' ' . ($months == 1 ? 'month' : 'months') . ' ago';
        }

        $years = floor($diff / 31536000);
        return $years . ' ' . ($years == 1 ? 'year' : 'years') . ' ago';
    }
}