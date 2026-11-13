<?php

namespace App\Enums;

enum RecipeItemRole: string
{
    case BaseSpirit = 'base_spirit';
    case Modifier = 'modifier';
    case Sweetener = 'sweetener';
    case Bitters = 'bitters';
    case Garnish = 'garnish';
}
