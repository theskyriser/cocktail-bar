<?php

namespace App\Enums;

/**
 * Whether a recipe line accepts a stand-in. The design handoff reads the group
 * off the ingredient; this schema resolves it through the pairwise
 * `ingredient_substitute` table instead, so AnyInGroup means any ingredient
 * linked to this one as a substitute.
 */
enum SubstitutionRule: string
{
    case Exact = 'exact';
    case AnyInGroup = 'any_in_group';
}
