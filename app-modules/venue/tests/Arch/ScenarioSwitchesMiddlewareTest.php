<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Scenario-switches middleware coverage
|--------------------------------------------------------------------------
|
| O invariante do ticket ("os três switches globais recusam QUALQUER rota do
| venue") não pode viver só de prosa no docblock de ApplyScenarioSwitches —
| cada rota nova do venue precisa opt-in explícito no alias
| `venue.scenario-switches`, e nada além deste teste falha quando alguém
| esquece. Reflete sobre as rotas já registradas em vez de reimplementar a
| lista de endpoints, para nunca ficar desatualizado.
|
*/

test('every registered venue route applies the scenario-switches middleware', function (): void {
    $venueRoutes = collect(Route::getRoutes())
        ->filter(fn ($route): bool => str_starts_with((string) $route->getActionName(), 'He4rt\\Venue\\'));

    expect($venueRoutes)->not->toBeEmpty();

    foreach ($venueRoutes as $route) {
        expect('venue.scenario-switches')->toBeIn($route->gatherMiddleware());
    }
});
