<?php

namespace App\Enums;

enum RecipeRole: string
{
    case Base = 'base';
    case Modifier = 'modifier';
    case Sweetener = 'sweetener';
    case Bitters = 'bitters';
    case Garnish = 'garnish';
}
