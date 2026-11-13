<?php

use App\Intake\NameNormaliser;

it('normalises a typed name to its lookup key', function (string $typed, string $expected) {
    expect(NameNormaliser::normalise($typed))->toBe($expected);
})->with([
    'case only' => ['Campari', 'campari'],
    'accents' => ['Crème de Cassis', 'creme de cassis'],
    'umlaut' => ['Jägermeister', 'jagermeister'],
    'apostrophe' => ["Gordon's Gin", 'gordons gin'],
    'full stops' => ['St. Germain', 'st germain'],
    'hyphen' => ['Seven-Up', 'seven up'],
    'collapsed whitespace' => ["  Sweet   Vermouth \n", 'sweet vermouth'],
    'already normal' => ['simple syrup', 'simple syrup'],
]);

it('does not singularise, because bitters is not a bitter', function () {
    expect(NameNormaliser::normalise('Angostura Bitters'))->toBe('angostura bitters');
});

it('does not singularise a brand ending in s', function () {
    expect(NameNormaliser::normalise('Gordons'))->toBe('gordons');
});

it('keeps distinct ingredients distinct', function () {
    expect(NameNormaliser::normalise('Sweet Vermouth'))
        ->not->toBe(NameNormaliser::normalise('Dry Vermouth'));
});
