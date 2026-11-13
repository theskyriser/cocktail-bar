<?php

namespace App\Enums;

enum AlcoholParentType: string
{
    case Whiskey = 'whiskey';
    case Gin = 'gin';
    case Rum = 'rum';
    case Vodka = 'vodka';
    case Tequila = 'tequila';
    case Brandy = 'brandy';
    case Liqueur = 'liqueur';
    case Vermouth = 'vermouth';
    case Other = 'other';
}
