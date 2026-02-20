<?php

namespace App\Service;

final class TextSimilarity
{
    public static function normalize(string $text): string
    {
        $t = mb_strtolower(trim($text));
        $t = preg_replace('/\s+/', ' ', $t);
        return $t ?? '';
    }

    /**
     * Lexical similarity using 5-word shingles Jaccard.
     * @return array{score: float, common: string[]}
     */
    public static function lexicalShingles(string $a, string $b, int $k = 5): array
    {
        $ta = self::tokenize(self::normalize($a));
        $tb = self::tokenize(self::normalize($b));

        $sa = self::shingles($ta, $k);
        $sb = self::shingles($tb, $k);

        if (!$sa || !$sb) return ['score' => 0.0, 'common' => []];

        $inter = array_values(array_intersect($sa, $sb));
        $union = array_values(array_unique(array_merge($sa, $sb)));

        $score = count($union) ? (count($inter) / count($union)) : 0.0;

        // take top common shingles as “highlights”
        $common = array_slice(array_values(array_unique($inter)), 0, 8);

        return ['score' => (float) $score, 'common' => $common];
    }

    /**
     * Structure similarity: compare sentence lengths pattern (rough).
     */
    public static function structureScore(string $a, string $b): float
    {
        $sa = self::sentenceLengths($a);
        $sb = self::sentenceLengths($b);
        if (count($sa) < 2 || count($sb) < 2) return 0.0;

        // compare mean + std-ish (simple)
        [$ma,$va] = self::meanVar($sa);
        [$mb,$vb] = self::meanVar($sb);

        $dm = abs($ma - $mb) / max(1.0, max($ma,$mb));
        $dv = abs($va - $vb) / max(1.0, max($va,$vb));

        $raw = 1.0 - min(1.0, (0.7*$dm + 0.3*$dv));
        return max(0.0, $raw);
    }

    private static function tokenize(string $t): array
    {
        // remove punctuation except apostrophe
        $t = preg_replace('/[^\p{L}\p{N}\' ]+/u', ' ', $t) ?? '';
        $parts = array_values(array_filter(explode(' ', $t), fn($x)=>$x!=='' && mb_strlen($x)>=2));
        // remove ultra-common words (tiny stoplist)
        $stop = ['le','la','les','de','des','du','un','une','et','ou','en','dans','pour','avec','sur','au','aux','ce','cet','cette','est','sont','que','qui','quoi','donc','car','par'];
        return array_values(array_filter($parts, fn($w)=>!in_array($w,$stop,true)));
    }

    private static function shingles(array $tokens, int $k): array
    {
        $n = count($tokens);
        if ($n < $k) return [];
        $out = [];
        for ($i=0;$i<=$n-$k;$i++){
            $out[] = implode(' ', array_slice($tokens, $i, $k));
        }
        return array_values(array_unique($out));
    }

    private static function sentenceLengths(string $t): array
    {
        $t = trim($t);
        if ($t === '') return [];
        $sentences = preg_split('/[.!?]+/u', $t) ?: [];
        $lens = [];
        foreach ($sentences as $s) {
            $s = trim($s);
            if ($s === '') continue;
            $lens[] = mb_strlen($s);
        }
        return $lens;
    }

    /** @return array{0: float, 1: float} */
    private static function meanVar(array $xs): array
    {
        $n = count($xs);
        $m = array_sum($xs)/max(1,$n);
        $v = 0.0;
        foreach ($xs as $x) $v += ($x-$m)*($x-$m);
        $v = $v/max(1,$n);
        return [$m,$v];
    }
}
