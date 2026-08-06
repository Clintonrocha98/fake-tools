<?php

declare(strict_types=1);

use App\Models\BaseModel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Scenarios sub-domain architecture rules
|--------------------------------------------------------------------------
|
| Escopadas em `He4rt\FakeStarkbank\Scenarios\Models`/`Enums`/`DTOs`, nunca no
| namespace inteiro: um `toExtend`/`toImplement` module-wide falharia nas
| Actions e no Middleware do mesmo sub-domínio.
|
| `HasIcon` fica de fora da regra dos enums: os quatro enums de desfecho
| implementam `PixLegOutcomeContract`, que não o exige — o ícone é da PERNA, e
| repeti-lo em cada desfecho só encheria o card de cenários.
|
*/

arch('os models de cenário estendem o BaseModel compartilhado')
    ->expect('He4rt\FakeStarkbank\Scenarios\Models')
    ->toExtend(BaseModel::class);

arch('os enums de cenário implementam os contratos que o painel lê')
    ->expect('He4rt\FakeStarkbank\Scenarios\Enums')
    ->toBeEnums()
    ->toImplement([HasColor::class, HasDescription::class, HasLabel::class]);

arch('os DTOs de cenário são value objects imutáveis')
    ->expect('He4rt\FakeStarkbank\Scenarios\DTOs')
    ->toBeFinal()
    ->toBeReadonly();

/*
 * O invariante "os switches globais derrubam QUALQUER rota /v2/*" não pode
 * viver só na prosa do middleware: cada rota nova do módulo precisa do opt-in
 * explícito no alias, e nada além deste teste falha quando alguém esquece.
 * Reflete sobre as rotas já registradas para nunca ficar desatualizado.
 */
test('toda rota do fake-starkbank aplica o middleware de switches de cenário', function (): void {
    $rotas = collect(Route::getRoutes())
        ->filter(fn ($rota): bool => str_starts_with((string) $rota->getActionName(), 'He4rt\\FakeStarkbank\\'));

    expect($rotas)->not->toBeEmpty();

    foreach ($rotas as $rota) {
        expect('fake-starkbank.scenario-switches')->toBeIn($rota->gatherMiddleware());
    }
});
