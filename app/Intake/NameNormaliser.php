<?php

namespace App\Intake;

use Illuminate\Support\Str;

class NameNormaliser
{
    public static function normalise(string $value): string
    {
        $ascii = mb_strtolower(Str::ascii($value));
        $withoutPunctuation = str_replace(["'", '.'], '', $ascii);

        return trim(preg_replace('/[^a-z0-9]+/', ' ', $withoutPunctuation) ?? '');
    }
}
