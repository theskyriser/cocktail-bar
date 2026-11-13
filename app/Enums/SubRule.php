<?php

namespace App\Enums;

enum SubRule: string
{
    case Exact = 'exact';
    case Any = 'any';
    case Optional = 'optional';
}
