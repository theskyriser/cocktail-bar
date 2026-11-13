<?php

namespace App\Enums;

enum IngredientType: string
{
    case Spirit = 'spirit';
    case Liqueur = 'liqueur';
    case Vermouth = 'vermouth';
    case Fresh = 'fresh';
    case Mixer = 'mixer';
    case Bitters = 'bitters';
    case Syrup = 'syrup';
    case Garnish = 'garnish';
}
