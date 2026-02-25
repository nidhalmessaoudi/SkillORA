<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class MarkdownExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('extract_first_image', [$this, 'extractFirstImage']),
        ];
    }

    public function extractFirstImage(string $markdown): ?string
    {
        // Match markdown image syntax: ![alt text](url)
        if (preg_match('/!\[.*?\]\((.*?)\)/', $markdown, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
}
