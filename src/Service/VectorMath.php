<?php

namespace App\Service;

final class VectorMath
{
    /**
     * @param list<float> $a
     * @param list<float> $b
     */
    public static function cosine(array $a, array $b): float
    {
        if (count($a) === 0 || count($a) !== count($b)) return 0.0;

        $dot=0.0; $na=0.0; $nb=0.0;
        $n=count($a);
        for ($i=0;$i<$n;$i++){
            $dot += $a[$i]*$b[$i];
            $na  += $a[$i]*$a[$i];
            $nb  += $b[$i]*$b[$i];
        }
        if ($na==0.0 || $nb==0.0) return 0.0;
        return $dot/(sqrt($na)*sqrt($nb));
    }
}
