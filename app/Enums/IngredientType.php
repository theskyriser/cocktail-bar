<?php

namespace App\Enums;

enum IngredientType: string
{
    case Spirit = 'spirit';
    case Liqueur = 'liqueur';
    case Fortified = 'fortified';
    case Bitters = 'bitters';
    case Fresh = 'fresh';
    case Juice = 'juice';
    case Syrup = 'syrup';
    case Mixer = 'mixer';
    case Garnish = 'garnish';
}
