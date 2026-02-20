<?php

namespace App\Service;

final class AiSuspicionScorer
{
    /**
     * @return array{percent:int, signals:array<string,mixed>}
     */
    public function score(string $text): array
    {
        $t = trim($text);

        // ✅ seuil un peu plus réaliste
        if (mb_strlen($t) < 120) {
            return ['percent' => 0, 'signals' => ['too_short' => true, 'len' => mb_strlen($t)]];
        }

        // ✅ important: inclure \n pour listes / SQL
        $sentences = preg_split('/[.!?]+|\n+/u', $t) ?: [];
        $sentences = array_values(array_filter(array_map('trim', $sentences), fn($s) => $s !== ''));

        $lens = array_map(fn($s) => mb_strlen($s), $sentences);
        $mean = array_sum($lens) / max(1, count($lens));

        $var = 0.0;
        foreach ($lens as $l) {
            $var += ($l - $mean) * ($l - $mean);
        }
        $var = $var / max(1, count($lens));

        // ✅ plus tolérant pour éviter des 0 bizarres
        $burst = 1.0 - min(1.0, $var / 8000.0);

        // ✅ patterns FR + EN
        $patterns = [
            'in conclusion','in summary','it is important to note','overall','moreover','furthermore',
            'en conclusion','en résumé','il est important de noter','dans l\'ensemble','de plus','par ailleurs','en effet'
        ];
        $hits = 0;
        foreach ($patterns as $p) {
            if (mb_stripos($t, $p) !== false) $hits++;
        }
        $patternScore = min(1.0, $hits / 3.0);

        // ✅ vocab FR + EN
        $academic = [
            'therefore','however','consequently','significant','noteworthy',
            'cependant','toutefois','donc','ainsi','par conséquent','significatif','notable'
        ];
        $ah = 0;
        foreach ($academic as $w) {
            if (mb_stripos($t, $w) !== false) $ah++;
        }
        $acadScore = min(1.0, $ah / 4.0);

        // ✅ régularité de longueur (signal IA)
        $regularity = 0.0;
        if (count($lens) >= 6) {
            $min = min($lens); $max = max($lens);
            $regularity = ($max > 0) ? (1.0 - min(1.0, ($max - $min) / $max)) : 0.0;
        }

        $final = (0.45 * $burst) + (0.25 * $patternScore) + (0.15 * $acadScore) + (0.15 * $regularity);
        $percent = (int) round(max(0, min(1, $final)) * 100);

        return [
            'percent' => $percent,
            'signals' => [
                'len' => mb_strlen($t),
                'sentences' => count($sentences),
                'burstiness' => (int) round($burst * 100),
                'pattern_hits' => $hits,
                'academic_hits' => $ah,
                'regularity' => (int) round($regularity * 100),
            ],
        ];
    }
}