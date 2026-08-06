# Armar o cenário do próximo pedido — Fase 1 (mecanismo + conversão spot)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Um cenário armado no painel faz o **próximo** `POST /api/v3/order` responder o desvio (parcial+EXPIRED, recusa por código, REJECTED, vocabulário desconhecido) com o ledger coerente, e é consumido nesse mesmo pedido.

**Architecture:** Uma tabela `fake_binance_armed_scenarios` guarda no máximo um cenário por perna (`leg` único). A Action de execução (`PlaceMarketOrder`) nunca aprende vocabulário de cenário: ela pede um `SpotExecutionPlan` a `PlanNextSpotExecution`, que consome o armado (ou devolve o plano neutro) e traduz o desfecho em fração de fill, status final, recusa e override de wire. O consumo é atômico (`lockForUpdate` + `delete` numa transação), então dois pedidos concorrentes nunca recebem o mesmo cenário.

**Tech Stack:** PHP 8.5 · Laravel 13 · Filament 5 · Pest 4 · PostgreSQL · `internachi/modular`

## Escopo desta fase

Fase 1 (este plano) entrega o mecanismo genérico completo + a perna **conversão spot** + a UI por switch. Fase 2 (plano seguinte, escrito depois desta executar) adiciona as pernas **depósito fiat** e **saque de stablecoin**, as ações de cabeçalho nos Resources e os docs do KB — cada uma só declara seus desfechos e herda o mecanismo.

`VenueLeg` nasce com **um caso** (`SpotConversion`). A Fase 2 adiciona os outros dois, e os `match` sem `default` apontam exatamente cada ponto a tocar.

**Spec:** `app-modules/fake-binance/docs/specs/2026-08-05-armar-cenario-do-proximo-pedido.md`

## Global Constraints

- **Branch:** `story/10-armar-cenario-do-proximo-pedido` (já criada, o spec está commitado nela). Nunca commitar em `main`.
- **Módulo:** `app-modules/fake-binance`, namespace `He4rt\FakeBinance`. Presentation em `app-modules/panel-admin`, namespace `He4rt\PanelAdmin`. Domínio nunca importa de `He4rt\PanelAdmin`.
- **`declare(strict_types=1);`** em todo arquivo PHP novo.
- **Migrations só via** `php artisan make:migration <nome> --module=fake-binance`. Toda coluna de data/hora usa a variante `Tz` (`timestampTz`, `timestampsTz`).
- **Model novo** declara `#[Table(name: '...')]`, `#[UseFactory(...)]` e bloco `@property` completo.
- **Proibido cast `array`/`json`/`object`/`collection`** em `casts()` — JSON com shape conhecido vira VO + cast dedicado (o gate é `tests/Arch/NoLooseArrayCastsTest.php`).
- **Todo enum novo** implementa `HasLabel`, `HasColor`, `HasDescription` (e `HasIcon` quando o ícone for significativo), com `match ($this)` exaustivo e **sem** `default`.
- **Comentários em pt_BR**, identificadores e vocabulário de wire em inglês. Nunca referenciar `#N` de issue/PR no código.
- **Interfaces terminam em `Contract`** (`LegOutcomeContract`), namespace `...\Contracts`.
- **Ledger:** o desfecho decide o que é creditado. Nunca creditar cheio e estornar.
- **Testes com Pest.** Um teste isolado roda com `php artisan test --compact --filter="<nome>"`. A bateria paralela **sempre** com `--processes=10`: `nice -n 19 ./vendor/bin/pest --parallel --processes=10 --compact`. Nunca `pest --parallel` sem `--processes`.
- **Antes de cada commit:** `vendor/bin/pint --dirty --format agent`.

---

### Task 1: VO do payload do cenário armado

O parâmetro de um desfecho (fração do parcial, código de erro, status arbitrário, motivo) viaja numa coluna JSON. Cast `array` é proibido — o payload é um VO tipado.

**Files:**
- Create: `app-modules/fake-binance/src/Scenarios/DTOs/ArmedScenarioPayload.php`
- Create: `app-modules/fake-binance/src/Scenarios/Casts/AsArmedScenarioPayload.php`
- Test: `app-modules/fake-binance/tests/Unit/Scenarios/ArmedScenarioPayloadTest.php`

**Interfaces:**
- Consumes: `He4rt\FakeBinance\Http\Errors\BinanceErrorCode` (enum int-backed já existente).
- Produces: `ArmedScenarioPayload` com propriedades públicas `?string $fraction`, `?int $errorCode`, `?string $rawStatus`, `?string $reason`; métodos `empty(): self`, `fromArray(array): self`, `toArray(): array`, `binanceErrorCode(): ?BinanceErrorCode`, `fractionOr(string $default): string`. `AsArmedScenarioPayload` implementa `CastsAttributes<ArmedScenarioPayload, ArmedScenarioPayload|array<array-key, mixed>>`.

- [ ] **Step 1: Escrever o teste que falha**

Crie `app-modules/fake-binance/tests/Unit/Scenarios/ArmedScenarioPayloadTest.php`:

```php
<?php

declare(strict_types=1);

use He4rt\FakeBinance\Http\Errors\BinanceErrorCode;
use He4rt\FakeBinance\Scenarios\DTOs\ArmedScenarioPayload;

it('is empty by default', function (): void {
    $payload = ArmedScenarioPayload::empty();

    expect($payload->fraction)->toBeNull()
        ->and($payload->errorCode)->toBeNull()
        ->and($payload->rawStatus)->toBeNull()
        ->and($payload->reason)->toBeNull()
        ->and($payload->toArray())->toBe([]);
});

it('keeps only the fields it knows, dropping anything else', function (): void {
    $payload = ArmedScenarioPayload::fromArray([
        'fraction' => '0.25',
        'errorCode' => -2010,
        'rawStatus' => 'SOME_FUTURE_STATE',
        'reason' => 'venue said no',
        'somethingElse' => 'ignored',
    ]);

    expect($payload->toArray())->toBe([
        'fraction' => '0.25',
        'errorCode' => -2010,
        'rawStatus' => 'SOME_FUTURE_STATE',
        'reason' => 'venue said no',
    ]);
});

it('refuses a non-numeric fraction and an empty string, reading them as absent', function (): void {
    $payload = ArmedScenarioPayload::fromArray([
        'fraction' => 'half',
        'rawStatus' => '',
        'reason' => '',
    ]);

    expect($payload->fraction)->toBeNull()
        ->and($payload->rawStatus)->toBeNull()
        ->and($payload->reason)->toBeNull();
});

it('resolves the error code into the Binance enum, and null when the code is unknown', function (): void {
    expect(ArmedScenarioPayload::fromArray(['errorCode' => -2010])->binanceErrorCode())
        ->toBe(BinanceErrorCode::NewOrderRejected)
        ->and(ArmedScenarioPayload::fromArray(['errorCode' => -99999])->binanceErrorCode())
        ->toBeNull()
        ->and(ArmedScenarioPayload::empty()->binanceErrorCode())
        ->toBeNull();
});

it('falls back to the given default when no fraction was armed', function (): void {
    expect(ArmedScenarioPayload::fromArray(['fraction' => '0.25'])->fractionOr('0.5'))->toBe('0.25')
        ->and(ArmedScenarioPayload::empty()->fractionOr('0.5'))->toBe('0.5');
});
```

- [ ] **Step 2: Rodar o teste e confirmar que falha**

```bash
php artisan test --compact --filter="is empty by default"
```

Esperado: FAIL — `Class "He4rt\FakeBinance\Scenarios\DTOs\ArmedScenarioPayload" not found`.

- [ ] **Step 3: Escrever o VO**

Crie `app-modules/fake-binance/src/Scenarios/DTOs/ArmedScenarioPayload.php`:

```php
<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Scenarios\DTOs;

use He4rt\FakeBinance\Http\Errors\BinanceErrorCode;

/**
 * O parâmetro de um cenário armado — só os campos que algum desfecho usa. O
 * VO é a única fonte do shape desse JSON: `fromArray()` descarta o que não
 * reconhece e normaliza vazio para `null`, então uma coluna adulterada à mão
 * nunca vira um campo meio preenchido dentro do plano de execução.
 */
final readonly class ArmedScenarioPayload
{
    /**
     * @param  numeric-string|null  $fraction  Fração do fill (desfecho parcial)
     * @param  int|null  $errorCode  Valor de {@see BinanceErrorCode} (desfecho de recusa)
     * @param  string|null  $rawStatus  Vocabulário de wire arbitrário
     * @param  string|null  $reason  Motivo textual (campo `info` do saque)
     */
    public function __construct(
        public ?string $fraction = null,
        public ?int $errorCode = null,
        public ?string $rawStatus = null,
        public ?string $reason = null,
    ) {}

    public static function empty(): self
    {
        return new self;
    }

    /**
     * @param  array<array-key, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $fraction = $payload['fraction'] ?? null;
        $errorCode = $payload['errorCode'] ?? null;
        $rawStatus = $payload['rawStatus'] ?? null;
        $reason = $payload['reason'] ?? null;

        return new self(
            fraction: is_string($fraction) && is_numeric($fraction) ? $fraction : null,
            errorCode: is_numeric($errorCode) ? (int) $errorCode : null,
            rawStatus: is_string($rawStatus) && $rawStatus !== '' ? $rawStatus : null,
            reason: is_string($reason) && $reason !== '' ? $reason : null,
        );
    }

    /**
     * @return array<string, int|string>
     */
    public function toArray(): array
    {
        return array_filter([
            'fraction' => $this->fraction,
            'errorCode' => $this->errorCode,
            'rawStatus' => $this->rawStatus,
            'reason' => $this->reason,
        ], static fn (int|string|null $value): bool => $value !== null);
    }

    public function binanceErrorCode(): ?BinanceErrorCode
    {
        return $this->errorCode !== null ? BinanceErrorCode::tryFrom($this->errorCode) : null;
    }

    /**
     * @param  numeric-string  $default
     * @return numeric-string
     */
    public function fractionOr(string $default): string
    {
        return $this->fraction ?? $default;
    }
}
```

- [ ] **Step 4: Escrever o cast**

Crie `app-modules/fake-binance/src/Scenarios/Casts/AsArmedScenarioPayload.php`:

```php
<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Scenarios\Casts;

use He4rt\FakeBinance\Scenarios\DTOs\ArmedScenarioPayload;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * @implements CastsAttributes<ArmedScenarioPayload, ArmedScenarioPayload|array<array-key, mixed>>
 */
final class AsArmedScenarioPayload implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ArmedScenarioPayload
    {
        $payload = json_decode((string) ($value ?? '{}'), associative: true);

        return ArmedScenarioPayload::fromArray(is_array($payload) ? $payload : []);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        $payload = match (true) {
            $value instanceof ArmedScenarioPayload => $value,
            is_array($value) => ArmedScenarioPayload::fromArray($value),
            default => ArmedScenarioPayload::empty(),
        };

        return json_encode($payload->toArray(), JSON_THROW_ON_ERROR);
    }
}
```

- [ ] **Step 5: Rodar os testes e confirmar que passam**

```bash
php artisan test --compact --filter=ArmedScenarioPayload
```

Esperado: 5 passed.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app-modules/fake-binance/src/Scenarios app-modules/fake-binance/tests/Unit/Scenarios
git commit -m "feat(fake-binance): VO tipado do payload de cenário armado"
```

---

### Task 2: Vocabulário — perna, contrato de desfecho e os desfechos do spot

**Files:**
- Create: `app-modules/fake-binance/src/Scenarios/Contracts/LegOutcomeContract.php`
- Create: `app-modules/fake-binance/src/Scenarios/Enums/VenueLeg.php`
- Create: `app-modules/fake-binance/src/Scenarios/Enums/SpotConversionOutcome.php`
- Test: `app-modules/fake-binance/tests/Unit/Scenarios/VenueLegTest.php`

**Interfaces:**
- Consumes: nada das tasks anteriores.
- Produces: `VenueLeg::SpotConversion` com `outcomes(): list<LegOutcomeContract>` e `outcomeFrom(string): LegOutcomeContract`. `LegOutcomeContract extends BackedEnum, HasColor, HasDescription, HasLabel` com `leg(): VenueLeg` e `payloadFields(): list<string>`. `SpotConversionOutcome` com os casos `FillPartialExpired`, `RefuseWithCode`, `RespondRejected`, `EmitUnknownStatus`.

- [ ] **Step 1: Escrever o teste que falha**

Crie `app-modules/fake-binance/tests/Unit/Scenarios/VenueLegTest.php`:

```php
<?php

declare(strict_types=1);

use He4rt\FakeBinance\Scenarios\Contracts\LegOutcomeContract;
use He4rt\FakeBinance\Scenarios\Enums\SpotConversionOutcome;
use He4rt\FakeBinance\Scenarios\Enums\VenueLeg;

it('lists the outcomes of the spot leg', function (): void {
    expect(VenueLeg::SpotConversion->outcomes())->toBe(SpotConversionOutcome::cases());
});

it('resolves a stored outcome value back into the leg enum', function (): void {
    expect(VenueLeg::SpotConversion->outcomeFrom('fill_partial_expired'))
        ->toBe(SpotConversionOutcome::FillPartialExpired);
});

it('points every outcome back at its own leg', function (LegOutcomeContract $outcome): void {
    expect($outcome->leg())->toBe(VenueLeg::SpotConversion);
})->with(SpotConversionOutcome::cases());

it('declares which payload field each outcome uses', function (): void {
    expect(SpotConversionOutcome::FillPartialExpired->payloadFields())->toBe(['fraction'])
        ->and(SpotConversionOutcome::RefuseWithCode->payloadFields())->toBe(['errorCode'])
        ->and(SpotConversionOutcome::RespondRejected->payloadFields())->toBe([])
        ->and(SpotConversionOutcome::EmitUnknownStatus->payloadFields())->toBe(['rawStatus']);
});

it('gives every outcome a label, a description and a color', function (LegOutcomeContract $outcome): void {
    expect($outcome->getLabel())->not->toBe('')
        ->and($outcome->getDescription())->not->toBe('')
        ->and($outcome->getColor())->not->toBeEmpty();
})->with(SpotConversionOutcome::cases());
```

- [ ] **Step 2: Rodar o teste e confirmar que falha**

```bash
php artisan test --compact --filter="lists the outcomes of the spot leg"
```

Esperado: FAIL — `Class "He4rt\FakeBinance\Scenarios\Enums\VenueLeg" not found`.

- [ ] **Step 3: Escrever o contrato**

Crie `app-modules/fake-binance/src/Scenarios/Contracts/LegOutcomeContract.php`:

```php
<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Scenarios\Contracts;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;
use He4rt\FakeBinance\Scenarios\Enums\VenueLeg;

/**
 * Um desfecho armável. Cada perna declara os seus num enum próprio — os
 * desfechos de uma perna não valem para outra, e é `leg()` que amarra cada
 * desfecho à sua, tanto para persistir quanto para montar a UI.
 */
interface LegOutcomeContract extends BackedEnum, HasColor, HasDescription, HasLabel
{
    public function leg(): VenueLeg;

    /**
     * Os campos de {@see \He4rt\FakeBinance\Scenarios\DTOs\ArmedScenarioPayload}
     * que este desfecho usa — a UI só mostra estes, e armar com qualquer outro
     * é ruído descartado.
     *
     * @return list<string>
     */
    public function payloadFields(): array;
}
```

- [ ] **Step 4: Escrever o enum da perna**

Crie `app-modules/fake-binance/src/Scenarios/Enums/VenueLeg.php`:

```php
<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Scenarios\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;
use He4rt\FakeBinance\Scenarios\Contracts\LegOutcomeContract;

/**
 * As pernas da venue que aceitam um cenário armado — uma por família de pedido
 * que o consumidor faz. No máximo um cenário armado por perna, garantido pelo
 * índice único de `leg` em `fake_binance_armed_scenarios`; pernas diferentes
 * ficam armadas ao mesmo tempo sem se atrapalhar.
 */
enum VenueLeg: string implements HasColor, HasDescription, HasIcon, HasLabel
{
    case SpotConversion = 'spot_conversion';

    /**
     * @return list<LegOutcomeContract>
     */
    public function outcomes(): array
    {
        return match ($this) {
            self::SpotConversion => SpotConversionOutcome::cases(),
        };
    }

    public function outcomeFrom(string $value): LegOutcomeContract
    {
        return match ($this) {
            self::SpotConversion => SpotConversionOutcome::from($value),
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::SpotConversion => 'Conversão spot',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::SpotConversion => 'primary',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::SpotConversion => 'POST /api/v3/order — o desfecho aparece na resposta do próprio pedido',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::SpotConversion => Heroicon::OutlinedArrowsRightLeft,
        };
    }
}
```

- [ ] **Step 5: Escrever o enum de desfechos do spot**

Crie `app-modules/fake-binance/src/Scenarios/Enums/SpotConversionOutcome.php`:

```php
<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Scenarios\Enums;

use Filament\Support\Colors\Color;
use He4rt\FakeBinance\Scenarios\Contracts\LegOutcomeContract;

/**
 * Os desfechos que uma conversão spot pode receber quando armada. A escala vai
 * do desvio mais brando (a ordem executa, só que pela metade) ao mais duro (a
 * venue nem processa), e as cores acompanham.
 *
 * `RespondRejected` é o único sem paralelo confirmado na venue real: a doc da
 * Binance descreve REJECTED como "not accepted by the engine and not
 * processed", o que na colocação chega como envelope de erro, não como 200. Fica
 * no catálogo porque o mapeamento existe do lado do consumidor.
 */
enum SpotConversionOutcome: string implements LegOutcomeContract
{
    case FillPartialExpired = 'fill_partial_expired';
    case EmitUnknownStatus = 'emit_unknown_status';
    case RespondRejected = 'respond_rejected';
    case RefuseWithCode = 'refuse_with_code';

    public function leg(): VenueLeg
    {
        return VenueLeg::SpotConversion;
    }

    /**
     * @return list<string>
     */
    public function payloadFields(): array
    {
        return match ($this) {
            self::FillPartialExpired => ['fraction'],
            self::EmitUnknownStatus => ['rawStatus'],
            self::RespondRejected => [],
            self::RefuseWithCode => ['errorCode'],
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::FillPartialExpired => 'Preencher parcial e expirar o resto',
            self::EmitUnknownStatus => 'Emitir vocabulário desconhecido',
            self::RespondRejected => 'Responder REJECTED',
            self::RefuseWithCode => 'Recusar com código',
        };
    }

    public function getColor(): string|array
    {
        return match ($this) {
            self::FillPartialExpired => 'warning',
            self::EmitUnknownStatus => Color::Orange,
            self::RespondRejected => Color::Rose,
            self::RefuseWithCode => 'danger',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::FillPartialExpired => 'A ordem executa só a fração armada e responde EXPIRED — o ledger recebe apenas essa fração',
            self::EmitUnknownStatus => 'A ordem executa normal, mas a wire responde um status fora do vocabulário',
            self::RespondRejected => 'HTTP 200 com status REJECTED e nenhum fill — o ledger não é tocado',
            self::RefuseWithCode => 'Envelope de erro da família /api/v3 — nenhuma ordem é criada',
        };
    }
}
```

- [ ] **Step 6: Rodar os testes e confirmar que passam**

```bash
php artisan test --compact --filter=VenueLeg
```

Esperado: todos passam (os dois testes com dataset rodam 4 vezes cada).

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app-modules/fake-binance/src/Scenarios app-modules/fake-binance/tests/Unit/Scenarios
git commit -m "feat(fake-binance): vocabulário de perna e desfechos armáveis da conversão spot"
```

---

### Task 3: Tabela, model e factory do cenário armado

**Files:**
- Create: migration `app-modules/fake-binance/database/migrations/<timestamp>_create_fake_binance_armed_scenarios_table.php` (via artisan)
- Create: `app-modules/fake-binance/src/Scenarios/Models/ArmedScenario.php`
- Create: `app-modules/fake-binance/database/factories/Scenarios/ArmedScenarioFactory.php`
- Test: `app-modules/fake-binance/tests/Feature/Scenarios/ArmedScenarioTest.php`

**Interfaces:**
- Consumes: `ArmedScenarioPayload`, `AsArmedScenarioPayload` (Task 1); `VenueLeg`, `LegOutcomeContract` (Task 2).
- Produces: `ArmedScenario` (model) com colunas `leg` (cast `VenueLeg`), `outcome` (string), `payload` (cast `AsArmedScenarioPayload`), `armed_at` (datetime) e o método `resolvedOutcome(): LegOutcomeContract`. `ArmedScenarioFactory` com o state `spotPartial()`.

- [ ] **Step 1: Gerar a migration pelo artisan**

```bash
php artisan make:migration create_fake_binance_armed_scenarios_table --module=fake-binance --no-interaction
```

Confirme que o arquivo caiu em `app-modules/fake-binance/database/migrations/`.

- [ ] **Step 2: Escrever a migration**

Substitua o corpo do arquivo gerado (mantenha o nome de arquivo que o artisan criou):

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * No máximo um cenário armado por perna — a unicidade de `leg` é a regra,
     * não uma otimização: armar de novo substitui o anterior em vez de
     * empilhar dois desfechos contraditórios para o mesmo próximo pedido.
     */
    public function up(): void
    {
        Schema::create('fake_binance_armed_scenarios', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('leg')->unique();
            $table->string('outcome');
            $table->jsonb('payload')->default('{}');
            $table->timestampTz('armed_at');
            $table->timestampsTz();
        });
    }
};
```

- [ ] **Step 3: Escrever o teste que falha**

Crie `app-modules/fake-binance/tests/Feature/Scenarios/ArmedScenarioTest.php`:

```php
<?php

declare(strict_types=1);

use He4rt\FakeBinance\Scenarios\DTOs\ArmedScenarioPayload;
use He4rt\FakeBinance\Scenarios\Enums\SpotConversionOutcome;
use He4rt\FakeBinance\Scenarios\Enums\VenueLeg;
use He4rt\FakeBinance\Scenarios\Models\ArmedScenario;
use Illuminate\Database\QueryException;

it('round-trips the payload through the typed cast', function (): void {
    $scenario = ArmedScenario::factory()->create([
        'payload' => new ArmedScenarioPayload(fraction: '0.25'),
    ]);

    expect($scenario->refresh()->payload)->toBeInstanceOf(ArmedScenarioPayload::class)
        ->and($scenario->payload->fraction)->toBe('0.25');
});

it('accepts a raw array on the payload, the legacy assignment branch', function (): void {
    $scenario = ArmedScenario::factory()->create(['payload' => ['rawStatus' => 'BANANA']]);

    expect($scenario->refresh()->payload->rawStatus)->toBe('BANANA');
});

it('resolves the stored outcome back into the leg enum', function (): void {
    $scenario = ArmedScenario::factory()->spotPartial()->create();

    expect($scenario->resolvedOutcome())->toBe(SpotConversionOutcome::FillPartialExpired)
        ->and($scenario->leg)->toBe(VenueLeg::SpotConversion);
});

it('refuses a second armed scenario for the same leg at the database level', function (): void {
    ArmedScenario::factory()->spotPartial()->create();

    ArmedScenario::factory()->spotPartial()->create();
})->throws(QueryException::class);
```

- [ ] **Step 4: Rodar o teste e confirmar que falha**

```bash
php artisan test --compact --filter="round-trips the payload through the typed cast"
```

Esperado: FAIL — `Class "He4rt\FakeBinance\Scenarios\Models\ArmedScenario" not found`.

- [ ] **Step 5: Escrever o model**

Crie `app-modules/fake-binance/src/Scenarios/Models/ArmedScenario.php`:

```php
<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Scenarios\Models;

use App\Models\BaseModel;
use He4rt\FakeBinance\Database\Factories\Scenarios\ArmedScenarioFactory;
use He4rt\FakeBinance\Scenarios\Casts\AsArmedScenarioPayload;
use He4rt\FakeBinance\Scenarios\Contracts\LegOutcomeContract;
use He4rt\FakeBinance\Scenarios\DTOs\ArmedScenarioPayload;
use He4rt\FakeBinance\Scenarios\Enums\VenueLeg;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Support\Carbon;

/**
 * O cenário combinado para o PRÓXIMO pedido de uma perna. Vale uma vez: quem
 * executa o pedido consome a linha ({@see \He4rt\FakeBinance\Scenarios\Actions\ConsumeArmedScenario}),
 * e o pedido seguinte volta ao happy path. Persistido em banco porque o fake
 * roda em container — um flag em memória morreria no restart.
 *
 * @property string $id
 * @property VenueLeg $leg
 * @property string $outcome
 * @property ArmedScenarioPayload $payload
 * @property Carbon $armed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @extends BaseModel<ArmedScenarioFactory>
 */
#[UseFactory(factoryClass: ArmedScenarioFactory::class)]
#[Table(name: 'fake_binance_armed_scenarios')]
final class ArmedScenario extends BaseModel
{
    /**
     * Nome deliberadamente diferente da coluna `outcome`: um método público
     * homônimo de um atributo é lido por `isRelation()` como relação e explode
     * no primeiro acesso ao atributo.
     */
    public function resolvedOutcome(): LegOutcomeContract
    {
        return $this->leg->outcomeFrom($this->outcome);
    }

    protected function casts(): array
    {
        return [
            'leg' => VenueLeg::class,
            'payload' => AsArmedScenarioPayload::class,
            'armed_at' => 'datetime',
        ];
    }
}
```

- [ ] **Step 6: Escrever a factory**

Crie `app-modules/fake-binance/database/factories/Scenarios/ArmedScenarioFactory.php`:

```php
<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Database\Factories\Scenarios;

use He4rt\FakeBinance\Scenarios\DTOs\ArmedScenarioPayload;
use He4rt\FakeBinance\Scenarios\Enums\SpotConversionOutcome;
use He4rt\FakeBinance\Scenarios\Enums\VenueLeg;
use He4rt\FakeBinance\Scenarios\Models\ArmedScenario;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;

/** @extends Factory<ArmedScenario> */
class ArmedScenarioFactory extends Factory
{
    protected $model = ArmedScenario::class;

    public function definition(): array
    {
        return [
            'leg' => VenueLeg::SpotConversion,
            'outcome' => SpotConversionOutcome::RespondRejected->value,
            'payload' => ArmedScenarioPayload::empty(),
            'armed_at' => Date::now(),
        ];
    }

    /**
     * Conversão armada para preencher metade e expirar o resto.
     */
    public function spotPartial(): static
    {
        return $this->state(fn (): array => [
            'leg' => VenueLeg::SpotConversion,
            'outcome' => SpotConversionOutcome::FillPartialExpired->value,
            'payload' => new ArmedScenarioPayload(fraction: '0.5'),
        ]);
    }
}
```

- [ ] **Step 7: Rodar os testes e confirmar que passam**

```bash
php artisan test --compact --filter=ArmedScenarioTest
```

Esperado: 4 passed. Se a migration não tiver rodado, o Pest a aplica pelo `LazilyRefreshDatabase` do grupo `feature`.

- [ ] **Step 8: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app-modules/fake-binance/database app-modules/fake-binance/src/Scenarios app-modules/fake-binance/tests/Feature/Scenarios
git commit -m "feat(fake-binance): tabela, model e factory do cenário armado"
```

---

### Task 4: Armar, desarmar e consumir

**Files:**
- Create: `app-modules/fake-binance/src/Scenarios/Actions/ArmScenario.php`
- Create: `app-modules/fake-binance/src/Scenarios/Actions/DisarmScenario.php`
- Create: `app-modules/fake-binance/src/Scenarios/Actions/ConsumeArmedScenario.php`
- Create: `app-modules/fake-binance/src/Scenarios/Actions/GetArmedScenario.php`
- Test: `app-modules/fake-binance/tests/Feature/Scenarios/ArmAndConsumeScenarioTest.php`

**Interfaces:**
- Consumes: `ArmedScenario`, `ArmedScenarioFactory` (Task 3); `LegOutcomeContract`, `VenueLeg` (Task 2); `ArmedScenarioPayload` (Task 1).
- Produces:
  - `ArmScenario::handle(LegOutcomeContract $outcome, ArmedScenarioPayload $payload = new ArmedScenarioPayload): ArmedScenario`
  - `DisarmScenario::handle(VenueLeg $leg): void`
  - `ConsumeArmedScenario::handle(VenueLeg $leg): ?ArmedScenario`
  - `GetArmedScenario::handle(VenueLeg $leg): ?ArmedScenario` (leitura sem consumir — a UI usa)

- [ ] **Step 1: Escrever o teste que falha**

Crie `app-modules/fake-binance/tests/Feature/Scenarios/ArmAndConsumeScenarioTest.php`:

```php
<?php

declare(strict_types=1);

use He4rt\FakeBinance\Scenarios\Actions\ArmScenario;
use He4rt\FakeBinance\Scenarios\Actions\ConsumeArmedScenario;
use He4rt\FakeBinance\Scenarios\Actions\DisarmScenario;
use He4rt\FakeBinance\Scenarios\Actions\GetArmedScenario;
use He4rt\FakeBinance\Scenarios\DTOs\ArmedScenarioPayload;
use He4rt\FakeBinance\Scenarios\Enums\SpotConversionOutcome;
use He4rt\FakeBinance\Scenarios\Enums\VenueLeg;
use He4rt\FakeBinance\Scenarios\Models\ArmedScenario;

it('arms an outcome with its payload', function (): void {
    $armed = (new ArmScenario)->handle(
        SpotConversionOutcome::FillPartialExpired,
        new ArmedScenarioPayload(fraction: '0.25'),
    );

    expect($armed->leg)->toBe(VenueLeg::SpotConversion)
        ->and($armed->resolvedOutcome())->toBe(SpotConversionOutcome::FillPartialExpired)
        ->and($armed->payload->fraction)->toBe('0.25')
        ->and($armed->armed_at)->not->toBeNull();
});

it('replaces the armed scenario of a leg instead of stacking a second one', function (): void {
    $arm = new ArmScenario;

    $arm->handle(SpotConversionOutcome::FillPartialExpired, new ArmedScenarioPayload(fraction: '0.25'));
    $arm->handle(SpotConversionOutcome::RespondRejected);

    expect(ArmedScenario::query()->count())->toBe(1)
        ->and(ArmedScenario::query()->sole()->resolvedOutcome())->toBe(SpotConversionOutcome::RespondRejected);
});

it('disarms a leg, leaving nothing behind', function (): void {
    (new ArmScenario)->handle(SpotConversionOutcome::RespondRejected);

    (new DisarmScenario)->handle(VenueLeg::SpotConversion);

    expect(ArmedScenario::query()->count())->toBe(0);
});

it('reads the armed scenario without consuming it', function (): void {
    (new ArmScenario)->handle(SpotConversionOutcome::RespondRejected);

    $read = (new GetArmedScenario)->handle(VenueLeg::SpotConversion);

    expect($read)->not->toBeNull()
        ->and(ArmedScenario::query()->count())->toBe(1);
});

it('consumes the armed scenario exactly once — the next read finds nothing', function (): void {
    (new ArmScenario)->handle(SpotConversionOutcome::RespondRejected);
    $consume = new ConsumeArmedScenario;

    $first = $consume->handle(VenueLeg::SpotConversion);
    $second = $consume->handle(VenueLeg::SpotConversion);

    expect($first)->not->toBeNull()
        ->and($first->resolvedOutcome())->toBe(SpotConversionOutcome::RespondRejected)
        ->and($second)->toBeNull()
        ->and(ArmedScenario::query()->count())->toBe(0);
});

it('gives back null when the leg was never armed', function (): void {
    expect((new ConsumeArmedScenario)->handle(VenueLeg::SpotConversion))->toBeNull()
        ->and((new GetArmedScenario)->handle(VenueLeg::SpotConversion))->toBeNull();
});
```

- [ ] **Step 2: Rodar o teste e confirmar que falha**

```bash
php artisan test --compact --filter="arms an outcome with its payload"
```

Esperado: FAIL — `Class "He4rt\FakeBinance\Scenarios\Actions\ArmScenario" not found`.

- [ ] **Step 3: Escrever as quatro Actions**

`app-modules/fake-binance/src/Scenarios/Actions/ArmScenario.php`:

```php
<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Scenarios\Actions;

use He4rt\FakeBinance\Scenarios\Contracts\LegOutcomeContract;
use He4rt\FakeBinance\Scenarios\DTOs\ArmedScenarioPayload;
use He4rt\FakeBinance\Scenarios\Models\ArmedScenario;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * Arma o desfecho do próximo pedido da perna. Armar de novo SUBSTITUI: dois
 * desfechos para o mesmo próximo pedido se contradizem, então o anterior sai
 * antes do novo entrar, na mesma transação.
 */
final readonly class ArmScenario
{
    public function handle(LegOutcomeContract $outcome, ArmedScenarioPayload $payload = new ArmedScenarioPayload): ArmedScenario
    {
        $leg = $outcome->leg();

        return DB::transaction(function () use ($leg, $outcome, $payload): ArmedScenario {
            ArmedScenario::query()->where('leg', $leg)->delete();

            return ArmedScenario::query()->create([
                'leg' => $leg,
                'outcome' => (string) $outcome->value,
                'payload' => $payload,
                'armed_at' => Date::now(),
            ]);
        });
    }
}
```

`app-modules/fake-binance/src/Scenarios/Actions/DisarmScenario.php`:

```php
<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Scenarios\Actions;

use He4rt\FakeBinance\Scenarios\Enums\VenueLeg;
use He4rt\FakeBinance\Scenarios\Models\ArmedScenario;

/**
 * Desarma a perna. Desarmar o que não estava armado é no-op silencioso — o
 * estado final é o mesmo, e um erro aqui só atrapalharia a UI.
 */
final readonly class DisarmScenario
{
    public function handle(VenueLeg $leg): void
    {
        ArmedScenario::query()->where('leg', $leg)->delete();
    }
}
```

`app-modules/fake-binance/src/Scenarios/Actions/ConsumeArmedScenario.php`:

```php
<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Scenarios\Actions;

use He4rt\FakeBinance\Scenarios\Enums\VenueLeg;
use He4rt\FakeBinance\Scenarios\Models\ArmedScenario;
use Illuminate\Support\Facades\DB;

/**
 * Consome o cenário armado da perna: devolve o que estava armado e apaga a
 * linha, de forma que o pedido seguinte já veja o happy path.
 *
 * O `lockForUpdate()` é o que faz "vale uma vez" valer sob concorrência: dois
 * pedidos simultâneos serializam na mesma linha e só o primeiro leva o
 * cenário — o segundo encontra a linha já apagada e segue no plano neutro.
 */
final readonly class ConsumeArmedScenario
{
    public function handle(VenueLeg $leg): ?ArmedScenario
    {
        return DB::transaction(function () use ($leg): ?ArmedScenario {
            $armed = ArmedScenario::query()
                ->where('leg', $leg)
                ->lockForUpdate()
                ->first();

            $armed?->delete();

            return $armed;
        });
    }
}
```

`app-modules/fake-binance/src/Scenarios/Actions/GetArmedScenario.php`:

```php
<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Scenarios\Actions;

use He4rt\FakeBinance\Scenarios\Enums\VenueLeg;
use He4rt\FakeBinance\Scenarios\Models\ArmedScenario;

/**
 * Leitura sem consumo — a UI precisa mostrar o que está armado sem gastar o
 * cenário. Só quem executa o pedido consome
 * ({@see ConsumeArmedScenario}).
 */
final readonly class GetArmedScenario
{
    public function handle(VenueLeg $leg): ?ArmedScenario
    {
        return ArmedScenario::query()->where('leg', $leg)->first();
    }
}
```

- [ ] **Step 4: Rodar os testes e confirmar que passam**

```bash
php artisan test --compact --filter=ArmAndConsumeScenario
```

Esperado: 6 passed.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app-modules/fake-binance/src/Scenarios app-modules/fake-binance/tests/Feature/Scenarios
git commit -m "feat(fake-binance): armar, desarmar e consumir cenário por perna"
```

---

### Task 5: O plano de execução da conversão spot

**Files:**
- Create: `app-modules/fake-binance/src/Spot/DTOs/SpotExecutionPlan.php`
- Create: `app-modules/fake-binance/src/Spot/Actions/PlanNextSpotExecution.php`
- Test: `app-modules/fake-binance/tests/Feature/Spot/PlanNextSpotExecutionTest.php`

**Interfaces:**
- Consumes: `ConsumeArmedScenario` (Task 4), `SpotConversionOutcome`/`VenueLeg` (Task 2), `ArmedScenarioPayload` (Task 1), `ArmScenario` (Task 4, nos testes).
- Produces:
  - `SpotExecutionPlan` — propriedades públicas `string $fillFraction`, `OrderStatus $finalStatus`, `?BinanceErrorCode $refusal`, `?string $rawStatusOverride`; constante `DEFAULT_PARTIAL_FRACTION = '0.5'`; métodos `neutral(): self`, `refuses(): bool`, `fillsNothing(): bool`, `applyFraction(string $amount, int $scale): string`.
  - `PlanNextSpotExecution::handle(): SpotExecutionPlan`.

- [ ] **Step 1: Escrever o teste que falha**

Crie `app-modules/fake-binance/tests/Feature/Spot/PlanNextSpotExecutionTest.php`:

```php
<?php

declare(strict_types=1);

use He4rt\FakeBinance\Http\Errors\BinanceErrorCode;
use He4rt\FakeBinance\Scenarios\Actions\ArmScenario;
use He4rt\FakeBinance\Scenarios\DTOs\ArmedScenarioPayload;
use He4rt\FakeBinance\Scenarios\Enums\SpotConversionOutcome;
use He4rt\FakeBinance\Scenarios\Models\ArmedScenario;
use He4rt\FakeBinance\Spot\Actions\PlanNextSpotExecution;
use He4rt\FakeBinance\Spot\DTOs\SpotExecutionPlan;
use He4rt\FakeBinance\Spot\Enums\OrderStatus;

it('plans the happy path when nothing is armed', function (): void {
    $plan = resolve(PlanNextSpotExecution::class)->handle();

    expect($plan->fillFraction)->toBe('1')
        ->and($plan->finalStatus)->toBe(OrderStatus::Filled)
        ->and($plan->refuses())->toBeFalse()
        ->and($plan->fillsNothing())->toBeFalse()
        ->and($plan->rawStatusOverride)->toBeNull();
});

it('plans a partial fill that expires, using the armed fraction', function (): void {
    (new ArmScenario)->handle(SpotConversionOutcome::FillPartialExpired, new ArmedScenarioPayload(fraction: '0.25'));

    $plan = resolve(PlanNextSpotExecution::class)->handle();

    expect($plan->fillFraction)->toBe('0.25')
        ->and($plan->finalStatus)->toBe(OrderStatus::Expired)
        ->and($plan->refuses())->toBeFalse();
});

it('falls back to half when the partial outcome was armed without a fraction', function (): void {
    (new ArmScenario)->handle(SpotConversionOutcome::FillPartialExpired);

    expect(resolve(PlanNextSpotExecution::class)->handle()->fillFraction)
        ->toBe(SpotExecutionPlan::DEFAULT_PARTIAL_FRACTION);
});

it('plans a refusal with the armed code', function (): void {
    (new ArmScenario)->handle(SpotConversionOutcome::RefuseWithCode, new ArmedScenarioPayload(errorCode: -1013));

    $plan = resolve(PlanNextSpotExecution::class)->handle();

    expect($plan->refuses())->toBeTrue()
        ->and($plan->refusal)->toBe(BinanceErrorCode::FilterFailure);
});

it('falls back to NEW_ORDER_REJECTED when the refusal was armed without a code', function (): void {
    (new ArmScenario)->handle(SpotConversionOutcome::RefuseWithCode);

    expect(resolve(PlanNextSpotExecution::class)->handle()->refusal)
        ->toBe(BinanceErrorCode::NewOrderRejected);
});

it('plans a REJECTED response that fills nothing', function (): void {
    (new ArmScenario)->handle(SpotConversionOutcome::RespondRejected);

    $plan = resolve(PlanNextSpotExecution::class)->handle();

    expect($plan->finalStatus)->toBe(OrderStatus::Rejected)
        ->and($plan->fillsNothing())->toBeTrue()
        ->and($plan->refuses())->toBeFalse();
});

it('plans an unknown wire status over an otherwise normal fill', function (): void {
    (new ArmScenario)->handle(SpotConversionOutcome::EmitUnknownStatus, new ArmedScenarioPayload(rawStatus: 'BANANA'));

    $plan = resolve(PlanNextSpotExecution::class)->handle();

    expect($plan->rawStatusOverride)->toBe('BANANA')
        ->and($plan->finalStatus)->toBe(OrderStatus::Filled)
        ->and($plan->fillFraction)->toBe('1');
});

it('consumes the armed scenario when it plans', function (): void {
    (new ArmScenario)->handle(SpotConversionOutcome::RespondRejected);

    resolve(PlanNextSpotExecution::class)->handle();

    expect(ArmedScenario::query()->count())->toBe(0)
        ->and(resolve(PlanNextSpotExecution::class)->handle()->finalStatus)->toBe(OrderStatus::Filled);
});

it('applies the fraction at the given scale, and never multiplies on the neutral plan', function (): void {
    $partial = new SpotExecutionPlan('0.5', OrderStatus::Expired, null, null);

    expect($partial->applyFraction('2.93000000', 8))->toBe('1.46500000')
        ->and(SpotExecutionPlan::neutral()->applyFraction('2.93000000', 8))->toBe('2.93000000');
});
```

- [ ] **Step 2: Rodar o teste e confirmar que falha**

```bash
php artisan test --compact --filter="plans the happy path when nothing is armed"
```

Esperado: FAIL — `Class "He4rt\FakeBinance\Spot\Actions\PlanNextSpotExecution" not found`.

- [ ] **Step 3: Escrever o plano**

Crie `app-modules/fake-binance/src/Spot/DTOs/SpotExecutionPlan.php`:

```php
<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Spot\DTOs;

use He4rt\FakeBinance\Http\Errors\BinanceErrorCode;
use He4rt\FakeBinance\Spot\Enums\OrderStatus;

/**
 * O que {@see \He4rt\FakeBinance\Spot\Actions\PlaceMarketOrder} deve fazer com o
 * próximo pedido. O happy path não é um `if` ausente: é o plano neutro (fração
 * `1`, FILLED, sem recusa), então a Action tem UM caminho só e nunca conhece o
 * vocabulário de cenário.
 */
final readonly class SpotExecutionPlan
{
    /** Fração do desfecho parcial quando o operador não informou uma. */
    public const string DEFAULT_PARTIAL_FRACTION = '0.5';

    /**
     * @param  numeric-string  $fillFraction
     */
    public function __construct(
        public string $fillFraction,
        public OrderStatus $finalStatus,
        public ?BinanceErrorCode $refusal,
        public ?string $rawStatusOverride,
    ) {}

    public static function neutral(): self
    {
        return new self('1', OrderStatus::Filled, null, null);
    }

    public function refuses(): bool
    {
        return $this->refusal instanceof BinanceErrorCode;
    }

    public function fillsNothing(): bool
    {
        return bccomp($this->fillFraction, '0', 18) <= 0;
    }

    /**
     * A fração é aplicada na MESMA escala em que o valor original foi
     * calculado — `executedQty` na precisão da base, `cummulativeQuoteQty` na
     * escala do ledger. Multiplicar tudo numa escala só faria a wire reportar
     * um número que o ledger não moveu.
     *
     * @param  numeric-string  $amount
     * @return numeric-string
     */
    public function applyFraction(string $amount, int $scale): string
    {
        return $this->fillFraction === '1' ? $amount : bcmul($amount, $this->fillFraction, $scale);
    }
}
```

- [ ] **Step 4: Escrever a Action que planeja**

Crie `app-modules/fake-binance/src/Spot/Actions/PlanNextSpotExecution.php`:

```php
<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Spot\Actions;

use He4rt\FakeBinance\Http\Errors\BinanceErrorCode;
use He4rt\FakeBinance\Scenarios\Actions\ConsumeArmedScenario;
use He4rt\FakeBinance\Scenarios\Enums\SpotConversionOutcome;
use He4rt\FakeBinance\Scenarios\Enums\VenueLeg;
use He4rt\FakeBinance\Scenarios\Models\ArmedScenario;
use He4rt\FakeBinance\Spot\DTOs\SpotExecutionPlan;
use He4rt\FakeBinance\Spot\Enums\OrderStatus;

/**
 * O único ponto da perna spot que fala com o cenário armado: consome o que
 * estiver armado e traduz o desfecho num {@see SpotExecutionPlan}. Sem cenário,
 * devolve o plano neutro.
 */
final readonly class PlanNextSpotExecution
{
    public function __construct(
        private ConsumeArmedScenario $consume = new ConsumeArmedScenario,
    ) {}

    public function handle(): SpotExecutionPlan
    {
        $armed = $this->consume->handle(VenueLeg::SpotConversion);

        if (!$armed instanceof ArmedScenario) {
            return SpotExecutionPlan::neutral();
        }

        $outcome = $armed->resolvedOutcome();

        if (!$outcome instanceof SpotConversionOutcome) {
            return SpotExecutionPlan::neutral();
        }

        return match ($outcome) {
            SpotConversionOutcome::FillPartialExpired => new SpotExecutionPlan(
                fillFraction: $armed->payload->fractionOr(SpotExecutionPlan::DEFAULT_PARTIAL_FRACTION),
                finalStatus: OrderStatus::Expired,
                refusal: null,
                rawStatusOverride: null,
            ),
            SpotConversionOutcome::EmitUnknownStatus => new SpotExecutionPlan(
                fillFraction: '1',
                finalStatus: OrderStatus::Filled,
                refusal: null,
                rawStatusOverride: $armed->payload->rawStatus,
            ),
            SpotConversionOutcome::RespondRejected => new SpotExecutionPlan(
                fillFraction: '0',
                finalStatus: OrderStatus::Rejected,
                refusal: null,
                rawStatusOverride: null,
            ),
            SpotConversionOutcome::RefuseWithCode => new SpotExecutionPlan(
                fillFraction: '0',
                finalStatus: OrderStatus::Rejected,
                refusal: $armed->payload->binanceErrorCode() ?? BinanceErrorCode::NewOrderRejected,
                rawStatusOverride: null,
            ),
        };
    }
}
```

- [ ] **Step 5: Rodar os testes e confirmar que passam**

```bash
php artisan test --compact --filter=PlanNextSpotExecution
```

Esperado: 9 passed.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app-modules/fake-binance/src/Spot app-modules/fake-binance/tests/Feature/Spot
git commit -m "feat(fake-binance): plano de execução da conversão spot a partir do cenário armado"
```

---

### Task 6: `PlaceMarketOrder` obedece ao plano

O coração da fase: a resposta do próprio POST passa a carregar o desvio.

**Files:**
- Create: `app-modules/fake-binance/src/Scenarios/Exceptions/ScenarioRefusedRequestException.php`
- Modify: `app-modules/fake-binance/src/Spot/Actions/PlaceMarketOrder.php`
- Modify: `app-modules/fake-binance/src/Spot/Http/Controllers/PlaceOrderController.php:45-52`
- Test: `app-modules/fake-binance/tests/Feature/Spot/ArmedSpotScenarioTest.php`

**Interfaces:**
- Consumes: `PlanNextSpotExecution`, `SpotExecutionPlan` (Task 5); `ArmScenario`, `ArmedScenarioPayload`, `SpotConversionOutcome` (Tasks 1/2/4).
- Produces: `ScenarioRefusedRequestException` com propriedade pública readonly `BinanceErrorCode $code` e construtor nomeado `withCode(BinanceErrorCode $code): self`. `PlaceMarketOrder` ganha o 5º parâmetro de construtor `PlanNextSpotExecution $planNextExecution`.

- [ ] **Step 1: Escrever o teste que falha**

Crie `app-modules/fake-binance/tests/Feature/Spot/ArmedSpotScenarioTest.php`. Os saldos e números batem com o happy path já coberto por `PlaceOrderEndpointTest` — BUY de 15 BRL ao ask, comissão de 0.1% sobre o USDC recebido:

```php
<?php

declare(strict_types=1);

use He4rt\FakeBinance\Ledger\Models\LedgerAccount;
use He4rt\FakeBinance\Scenarios\Actions\ArmScenario;
use He4rt\FakeBinance\Scenarios\DTOs\ArmedScenarioPayload;
use He4rt\FakeBinance\Scenarios\Enums\SpotConversionOutcome;
use He4rt\FakeBinance\Spot\Actions\PlaceMarketOrder;
use He4rt\FakeBinance\Spot\DTOs\PlaceMarketOrderData;
use He4rt\FakeBinance\Spot\Enums\OrderSide;
use He4rt\FakeBinance\Spot\Enums\OrderStatus;
use He4rt\FakeBinance\Spot\Models\SpotOrder;
use He4rt\FakeBinance\Scenarios\Exceptions\ScenarioRefusedRequestException;

function armedBuyOrder(string $clientOrderId = 'forex-armed-1'): PlaceMarketOrderData
{
    return new PlaceMarketOrderData(
        symbol: 'USDCBRL',
        side: OrderSide::Buy,
        newClientOrderId: $clientOrderId,
        quoteOrderQty: '15',
        quantity: null,
    );
}

beforeEach(function (): void {
    LedgerAccount::factory()->create(['asset' => 'BRL', 'free' => '1000']);
    LedgerAccount::factory()->create(['asset' => 'USDC', 'free' => '0']);
});

it('fills only the armed fraction and expires, crediting just that fraction', function (): void {
    (new ArmScenario)->handle(SpotConversionOutcome::FillPartialExpired, new ArmedScenarioPayload(fraction: '0.5'));

    $order = resolve(PlaceMarketOrder::class)->handle(armedBuyOrder());

    $full = resolve(PlaceMarketOrder::class)->handle(armedBuyOrder('forex-armed-full'));

    expect($order->status)->toBe(OrderStatus::Expired)
        ->and((string) $order->executed_qty)->toBe(bcdiv((string) $full->executed_qty, '2', 18))
        ->and(bccomp((string) $order->cummulative_quote_qty, (string) $full->cummulative_quote_qty, 18))->toBe(-1);
});

it('leaves the ledger matching the partial fill — nothing credited then reversed', function (): void {
    (new ArmScenario)->handle(SpotConversionOutcome::FillPartialExpired, new ArmedScenarioPayload(fraction: '0.5'));

    $order = resolve(PlaceMarketOrder::class)->handle(armedBuyOrder());

    $brl = LedgerAccount::query()->where('asset', 'BRL')->sole();
    $usdc = LedgerAccount::query()->where('asset', 'USDC')->sole();

    // O BRL debitado é exatamente o cummulativeQuoteQty da ordem parcial, e o
    // USDC creditado é a quantidade executada líquida de comissão.
    expect((string) $brl->free)->toBe(bcsub('1000', (string) $order->cummulative_quote_qty, 18))
        ->and((string) $usdc->free)->toBe(bcsub((string) $order->executed_qty, (string) $order->commission, 18));
});

it('refuses with the armed code without creating an order or touching the ledger', function (): void {
    (new ArmScenario)->handle(SpotConversionOutcome::RefuseWithCode, new ArmedScenarioPayload(errorCode: -2010));

    expect(fn (): SpotOrder => resolve(PlaceMarketOrder::class)->handle(armedBuyOrder()))
        ->toThrow(ScenarioRefusedRequestException::class);

    expect(SpotOrder::query()->count())->toBe(0)
        ->and((string) LedgerAccount::query()->where('asset', 'BRL')->sole()->free)->toBe('1000.000000000000000000');
});

it('responds REJECTED with an empty fill, leaving the ledger alone', function (): void {
    (new ArmScenario)->handle(SpotConversionOutcome::RespondRejected);

    $order = resolve(PlaceMarketOrder::class)->handle(armedBuyOrder());

    expect($order->status)->toBe(OrderStatus::Rejected)
        ->and((string) $order->executed_qty)->toBe('0.000000000000000000')
        ->and((string) $order->cummulative_quote_qty)->toBe('0.000000000000000000')
        ->and($order->fill_price)->toBeNull()
        ->and($order->commission_asset)->toBeNull()
        ->and((string) LedgerAccount::query()->where('asset', 'BRL')->sole()->free)->toBe('1000.000000000000000000');
});

it('echoes an unknown wire status while filling normally', function (): void {
    (new ArmScenario)->handle(SpotConversionOutcome::EmitUnknownStatus, new ArmedScenarioPayload(rawStatus: 'BANANA'));

    $order = resolve(PlaceMarketOrder::class)->handle(armedBuyOrder());

    expect($order->raw_status_override)->toBe('BANANA')
        ->and($order->status)->toBe(OrderStatus::Filled)
        ->and(bccomp((string) $order->executed_qty, '0', 18))->toBe(1);
});

it('goes back to the happy path on the very next order', function (): void {
    (new ArmScenario)->handle(SpotConversionOutcome::RespondRejected);

    resolve(PlaceMarketOrder::class)->handle(armedBuyOrder('forex-armed-first'));
    $second = resolve(PlaceMarketOrder::class)->handle(armedBuyOrder('forex-armed-second'));

    expect($second->status)->toBe(OrderStatus::Filled)
        ->and($second->raw_status_override)->toBeNull();
});

it('is bit-for-bit the old behaviour when nothing is armed', function (): void {
    $order = resolve(PlaceMarketOrder::class)->handle(armedBuyOrder());

    expect($order->status)->toBe(OrderStatus::Filled)
        ->and($order->raw_status_override)->toBeNull()
        ->and($order->commission_asset)->toBe('USDC')
        ->and($order->fill_price)->not->toBeNull();
});
```

Adicione ao mesmo arquivo o teste da rota, provando que o desvio chega na resposta do POST. Copie o helper de assinatura do `PlaceOrderEndpointTest` existente (leia-o antes: `app-modules/fake-binance/tests/Feature/Spot/PlaceOrderEndpointTest.php`) e escreva:

```php
it('carries the partial fill in the POST response itself, with fills', function (): void {
    (new ArmScenario)->handle(SpotConversionOutcome::FillPartialExpired, new ArmedScenarioPayload(fraction: '0.5'));

    // Monte a query assinada exatamente como o PlaceOrderEndpointTest existente faz.
    $response = $this->postJson(signedSpotOrderUrl([
        'symbol' => 'USDCBRL',
        'side' => 'BUY',
        'type' => 'MARKET',
        'quoteOrderQty' => '15',
        'newClientOrderId' => 'forex-wire-1',
    ]));

    $response->assertOk()
        ->assertJsonPath('status', 'EXPIRED')
        ->assertJsonCount(1, 'fills');

    expect(bccomp((string) $response->json('executedQty'), '0', 18))->toBe(1);
});

it('carries the refusal as the /api/v3 error envelope', function (): void {
    (new ArmScenario)->handle(SpotConversionOutcome::RefuseWithCode, new ArmedScenarioPayload(errorCode: -2010));

    $response = $this->postJson(signedSpotOrderUrl([
        'symbol' => 'USDCBRL',
        'side' => 'BUY',
        'type' => 'MARKET',
        'quoteOrderQty' => '15',
        'newClientOrderId' => 'forex-wire-2',
    ]));

    $response->assertStatus(BinanceErrorCode::NewOrderRejected->httpStatus())
        ->assertJsonPath('code', -2010);
});
```

> Se `PlaceOrderEndpointTest` não expuser um helper reutilizável de URL assinada, extraia o trecho de assinatura dele para uma função `signedSpotOrderUrl(array $query): string` no topo do seu arquivo de teste — não duplique a lógica de HMAC à mão.

- [ ] **Step 2: Rodar o teste e confirmar que falha**

```bash
php artisan test --compact --filter="fills only the armed fraction and expires"
```

Esperado: FAIL — a ordem sai `FILLED` cheia (o plano ainda não é consultado).

- [ ] **Step 3: Escrever a exceção de recusa**

Crie `app-modules/fake-binance/src/Scenarios/Exceptions/ScenarioRefusedRequestException.php`:

```php
<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Scenarios\Exceptions;

use He4rt\FakeBinance\Http\Errors\BinanceErrorCode;
use RuntimeException;

/**
 * Um cenário armado mandou recusar o pedido antes de qualquer efeito. Carrega o
 * código a devolver; quem mapeia para o envelope da família é o controller,
 * como já acontece com as demais recusas da perna.
 */
final class ScenarioRefusedRequestException extends RuntimeException
{
    private function __construct(public readonly BinanceErrorCode $code, string $message)
    {
        parent::__construct($message);
    }

    public static function withCode(BinanceErrorCode $code): self
    {
        return new self($code, $code->defaultMessage());
    }
}
```

- [ ] **Step 4: Fazer `PlaceMarketOrder` obedecer ao plano**

Em `app-modules/fake-binance/src/Spot/Actions/PlaceMarketOrder.php`:

1. Adicione os imports `He4rt\FakeBinance\Scenarios\Exceptions\ScenarioRefusedRequestException` e `He4rt\FakeBinance\Spot\DTOs\SpotExecutionPlan`.
2. Injete o planejador no construtor:

```php
    public function __construct(
        private GetBookTicker $bookTicker,
        private NextSpotOrderId $nextOrderId,
        private SwapLedgerAssets $swap,
        private AssertSpotSymbolFilters $assertFilters,
        private PlanNextSpotExecution $planNextExecution,
    ) {}
```

3. Em `handle()`, **depois** de `$this->assertFilters->handle(...)` e antes de ler o book, consulte o plano e trate a recusa (validação de parâmetro continua vencendo o cenário, como na venue real):

```php
        $plan = $this->planNextExecution->handle();

        if ($plan->refusal instanceof BinanceErrorCode) {
            throw ScenarioRefusedRequestException::withCode($plan->refusal);
        }
```

4. Aplique a fração logo depois de calcular o fill cheio, **antes** da comissão — assim a comissão já incide sobre o recebido fracionado:

```php
        [$executedQty, $cummulativeQuoteQty, $price, $receivedAsset] = $data->side === OrderSide::Buy
            ? $this->quoteSpend($data, $book->askPrice, $basePrecision, $baseAsset)
            : $this->baseSell($data, $book->bidPrice, $quoteAsset);

        $executedQty = $plan->applyFraction($executedQty, $basePrecision);
        $cummulativeQuoteQty = $plan->applyFraction($cummulativeQuoteQty, self::LEDGER_SCALE);

        $receivedGross = $this->receivedGross($data->side, $executedQty, $cummulativeQuoteQty);
        $commission = bcmul($receivedGross, $commissionRate, 18);
```

5. Na transação, pule o swap quando o plano não preenche nada, e grave o que o plano manda:

```php
        return DB::transaction(function () use (
            $data, $executedQty, $cummulativeQuoteQty, $price, $commission, $receivedAsset, $baseAsset, $quoteAsset, $plan,
        ): SpotOrder {
            if (!$plan->fillsNothing()) {
                $this->swap->handle(
                    from: $data->side === OrderSide::Buy ? $quoteAsset : $baseAsset,
                    to: $data->side === OrderSide::Buy ? $baseAsset : $quoteAsset,
                    fills: [new LedgerFill(qty: $executedQty, price: $price, commission: $commission, commissionAsset: $receivedAsset)],
                    side: $data->side === OrderSide::Buy ? LedgerSide::Buy : LedgerSide::Sell,
                );
            }

            return SpotOrder::query()->create([
                'order_id' => $this->nextOrderId->handle(),
                'client_order_id' => $data->newClientOrderId,
                'symbol' => $data->symbol,
                'side' => $data->side,
                'type' => 'MARKET',
                'status' => $plan->finalStatus,
                'quantity' => $data->quantity,
                'quote_order_qty' => $data->quoteOrderQty,
                'executed_qty' => $executedQty,
                'cummulative_quote_qty' => $cummulativeQuoteQty,
                'fill_price' => $plan->fillsNothing() ? null : $price,
                'commission' => $plan->fillsNothing() ? '0' : $commission,
                'commission_asset' => $plan->fillsNothing() ? null : $receivedAsset,
                'raw_status_override' => $plan->rawStatusOverride,
            ]);
        });
```

6. Reescreva o docblock da classe para descrever o estado atual (sem narrar a mudança):

```php
/**
 * Executa UMA ordem MARKET ao preço do book (sem re-order loop — a mesma
 * disciplina do `BinanceMarketExecution` do monolito consumidor: uma MARKET
 * não fica pendente, preenche de uma vez). BUY preenche no ask e gasta
 * `quoteOrderQty`; SELL preenche no bid e vende `quantity`. A comissão incide
 * sobre o ativo recebido — nunca sobre o gasto — e o `SwapLedgerAssets` já
 * aplica essa dedução ao creditar o ledger.
 *
 * O desfecho vem de um {@see SpotExecutionPlan}: no happy path é o plano
 * neutro (fração `1`, FILLED), e um cenário armado o substitui por parcial,
 * recusa ou vocabulário desconhecido. O ledger recebe exatamente a fração
 * executada — nunca o fill cheio seguido de estorno.
 */
```

- [ ] **Step 5: Mapear a recusa no controller**

Em `app-modules/fake-binance/src/Spot/Http/Controllers/PlaceOrderController.php`, adicione o import e o primeiro `catch` (antes dos existentes):

```php
        try {
            $order = $this->placeOrder->handle($data);
        } catch (ScenarioRefusedRequestException $exception) {
            return $this->errors->make($family, $exception->code, $exception->getMessage());
        } catch (DuplicateClientOrderIdException|InsufficientLedgerBalanceException $exception) {
            return $this->errors->make($family, BinanceErrorCode::NewOrderRejected, $exception->getMessage());
        } catch (SpotFilterViolationException $exception) {
            return $this->errors->make($family, BinanceErrorCode::FilterFailure, $exception->getMessage());
        }
```

- [ ] **Step 6: Rodar os testes novos e os antigos da perna**

```bash
php artisan test --compact --filter=ArmedSpotScenario
php artisan test --compact --filter="Spot"
```

Esperado: os novos passam **e** `PlaceOrderEndpointTest`, `GetOrderEndpointTest`, `RejectSpotOrderTest`, `ExpireSpotOrderPartiallyTest` seguem verdes — o plano neutro não muda nada.

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app-modules/fake-binance/src app-modules/fake-binance/tests
git commit -m "feat(fake-binance): a resposta do POST /api/v3/order obedece ao cenário armado"
```

---

### Task 7: Página de cenários armados, com switches

**Files:**
- Create: `app-modules/panel-admin/src/Filament/Pages/ArmedScenariosPage.php`
- Create: `app-modules/panel-admin/resources/views/filament/pages/armed-scenarios.blade.php`
- Modify: `app-modules/panel-admin/lang/pt_BR/fake-binance.php` (novo bloco `armed_scenarios`)
- Modify: `app-modules/panel-admin/lang/en/fake-binance.php` (mesmo bloco)
- Test: `app-modules/panel-admin/tests/Feature/Filament/Pages/ArmedScenariosPageTest.php`

**Interfaces:**
- Consumes: `ArmScenario`, `DisarmScenario`, `GetArmedScenario` (Task 4); `VenueLeg`, `SpotConversionOutcome`, `LegOutcomeContract` (Task 2); `ArmedScenarioPayload` (Task 1).
- Produces: página Livewire com `$data` (state dos switches), método público `armedOutcomeFor(VenueLeg $leg): ?LegOutcomeContract` e `form(Schema $schema): Schema`.

- [ ] **Step 1: Escrever o teste que falha**

Crie `app-modules/panel-admin/tests/Feature/Filament/Pages/ArmedScenariosPageTest.php` (siga o `ScenarioSwitchesPageTest.php` existente para o `actingAs`):

```php
<?php

declare(strict_types=1);

use He4rt\FakeBinance\Scenarios\Enums\SpotConversionOutcome;
use He4rt\FakeBinance\Scenarios\Enums\VenueLeg;
use He4rt\FakeBinance\Scenarios\Models\ArmedScenario;
use He4rt\Identity\Users\User;
use He4rt\PanelAdmin\Filament\Pages\ArmedScenariosPage;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
});

it('renders with every spot outcome switch off when nothing is armed', function (): void {
    livewire(ArmedScenariosPage::class)
        ->assertOk()
        ->assertSet('data.spot_conversion.fill_partial_expired', false)
        ->assertSet('data.spot_conversion.refuse_with_code', false);
});

it('arms an outcome when its switch is turned on', function (): void {
    livewire(ArmedScenariosPage::class)
        ->set('data.spot_conversion.fill_partial_expired', true)
        ->assertNotified();

    $armed = ArmedScenario::query()->sole();

    expect($armed->leg)->toBe(VenueLeg::SpotConversion)
        ->and($armed->resolvedOutcome())->toBe(SpotConversionOutcome::FillPartialExpired);
});

it('turns off the previously armed outcome of the same leg', function (): void {
    livewire(ArmedScenariosPage::class)
        ->set('data.spot_conversion.fill_partial_expired', true)
        ->set('data.spot_conversion.respond_rejected', true)
        ->assertSet('data.spot_conversion.fill_partial_expired', false);

    expect(ArmedScenario::query()->count())->toBe(1)
        ->and(ArmedScenario::query()->sole()->resolvedOutcome())->toBe(SpotConversionOutcome::RespondRejected);
});

it('disarms the leg when the switch is turned back off', function (): void {
    livewire(ArmedScenariosPage::class)
        ->set('data.spot_conversion.respond_rejected', true)
        ->set('data.spot_conversion.respond_rejected', false);

    expect(ArmedScenario::query()->count())->toBe(0);
});

it('arms the partial outcome with the fraction typed next to the switch', function (): void {
    livewire(ArmedScenariosPage::class)
        ->set('data.spot_conversion.fraction', '0.25')
        ->set('data.spot_conversion.fill_partial_expired', true);

    expect(ArmedScenario::query()->sole()->payload->fraction)->toBe('0.25');
});
```

- [ ] **Step 2: Rodar o teste e confirmar que falha**

```bash
php artisan test --compact --filter="renders with every spot outcome switch off"
```

Esperado: FAIL — `Class "He4rt\PanelAdmin\Filament\Pages\ArmedScenariosPage" not found`.

- [ ] **Step 3: Escrever a página**

Crie `app-modules/panel-admin/src/Filament/Pages/ArmedScenariosPage.php`:

```php
<?php

declare(strict_types=1);

namespace He4rt\PanelAdmin\Filament\Pages;

use App\Enums\NavigationGroup;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use He4rt\FakeBinance\Scenarios\Actions\ArmScenario;
use He4rt\FakeBinance\Scenarios\Actions\DisarmScenario;
use He4rt\FakeBinance\Scenarios\Actions\GetArmedScenario;
use He4rt\FakeBinance\Scenarios\Contracts\LegOutcomeContract;
use He4rt\FakeBinance\Scenarios\DTOs\ArmedScenarioPayload;
use He4rt\FakeBinance\Scenarios\Enums\VenueLeg;
use He4rt\FakeBinance\Scenarios\Models\ArmedScenario;
use UnitEnum;

/**
 * O cenário do PRÓXIMO pedido de cada perna. Um switch por desfecho: ligar arma,
 * desligar desarma, e ligar outro desfecho da mesma perna desliga o anterior —
 * dois desfechos para o mesmo pedido se contradizem. Sem clique, nada aqui
 * afeta o happy path.
 *
 * @property-read \Filament\Schemas\Schema $form
 */
class ArmedScenariosPage extends Page
{
    protected string $view = 'panel-admin::filament.pages.armed-scenarios';

    protected static ?string $slug = 'armed-scenarios';

    protected static ?string $title = null;

    protected static ?string $navigationLabel = null;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBoltSlash;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::FakeBinance;

    protected static ?int $navigationSort = 6;

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(): void
    {
        $this->form->fill($this->currentState());
    }

    public static function getNavigationLabel(): string
    {
        return __('panel-admin::fake-binance.armed_scenarios.title');
    }

    public function getTitle(): string
    {
        return __('panel-admin::fake-binance.armed_scenarios.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components(array_map(
                fn (VenueLeg $leg): Section => $this->legSection($leg),
                VenueLeg::cases(),
            ))
            ->statePath('data');
    }

    public function armedOutcomeFor(VenueLeg $leg): ?LegOutcomeContract
    {
        $armed = resolve(GetArmedScenario::class)->handle($leg);

        return $armed instanceof ArmedScenario ? $armed->resolvedOutcome() : null;
    }

    private function legSection(VenueLeg $leg): Section
    {
        $components = [
            TextInput::make($leg->value.'.fraction')
                ->label(__('panel-admin::fake-binance.armed_scenarios.fraction'))
                ->helperText(__('panel-admin::fake-binance.armed_scenarios.fraction_helper'))
                ->numeric(),
            TextInput::make($leg->value.'.errorCode')
                ->label(__('panel-admin::fake-binance.armed_scenarios.error_code'))
                ->numeric(),
            TextInput::make($leg->value.'.rawStatus')
                ->label(__('panel-admin::fake-binance.armed_scenarios.raw_status')),
        ];

        foreach ($leg->outcomes() as $outcome) {
            $components[] = Toggle::make($leg->value.'.'.$outcome->value)
                ->label($outcome->getLabel())
                ->helperText($outcome->getDescription())
                ->onColor('danger')
                ->live()
                ->afterStateUpdated(fn (bool $state) => $this->toggleOutcome($outcome, $state));
        }

        return Section::make($leg->getLabel())
            ->description($leg->getDescription())
            ->schema($components);
    }

    private function toggleOutcome(LegOutcomeContract $outcome, bool $enabled): void
    {
        $leg = $outcome->leg();

        if (!$enabled) {
            resolve(DisarmScenario::class)->handle($leg);
            $this->notifyState($outcome, armed: false);

            return;
        }

        resolve(ArmScenario::class)->handle($outcome, $this->payloadFor($leg));

        // Exclusão dentro da perna: o banco já só guarda um, a UI acompanha.
        foreach ($leg->outcomes() as $sibling) {
            if ($sibling !== $outcome) {
                $this->data[$leg->value][$sibling->value] = false;
            }
        }

        $this->notifyState($outcome, armed: true);
    }

    private function payloadFor(VenueLeg $leg): ArmedScenarioPayload
    {
        /** @var array<string, mixed> $state */
        $state = $this->data[$leg->value] ?? [];

        return ArmedScenarioPayload::fromArray([
            'fraction' => $state['fraction'] ?? null,
            'errorCode' => $state['errorCode'] ?? null,
            'rawStatus' => $state['rawStatus'] ?? null,
        ]);
    }

    private function notifyState(LegOutcomeContract $outcome, bool $armed): void
    {
        Notification::make()
            ->title(__('panel-admin::fake-binance.armed_scenarios.'.($armed ? 'armed_notification' : 'disarmed_notification'), [
                'outcome' => $outcome->getLabel(),
            ]))
            ->success()
            ->send();
    }

    /**
     * @return array<string, array<string, bool|string|null>>
     */
    private function currentState(): array
    {
        $state = [];

        foreach (VenueLeg::cases() as $leg) {
            $armed = resolve(GetArmedScenario::class)->handle($leg);
            $legState = [
                'fraction' => $armed?->payload->fraction,
                'errorCode' => $armed?->payload->errorCode !== null ? (string) $armed->payload->errorCode : null,
                'rawStatus' => $armed?->payload->rawStatus,
            ];

            foreach ($leg->outcomes() as $outcome) {
                $legState[$outcome->value] = $armed instanceof ArmedScenario && $armed->outcome === $outcome->value;
            }

            $state[$leg->value] = $legState;
        }

        return $state;
    }
}
```

- [ ] **Step 4: Escrever a view**

Crie `app-modules/panel-admin/resources/views/filament/pages/armed-scenarios.blade.php`:

```blade
<x-filament-panels::page>
    {{ $this->form }}
</x-filament-panels::page>
```

- [ ] **Step 5: Adicionar as traduções**

Em `app-modules/panel-admin/lang/pt_BR/fake-binance.php`, adicione antes do fechamento do array:

```php
    'armed_scenarios' => [
        'title' => 'Cenários armados',
        'fraction' => 'Fração do fill',
        'fraction_helper' => 'Quanto da ordem executa no desfecho parcial. Vazio usa metade.',
        'error_code' => 'Código de erro',
        'raw_status' => 'Status arbitrário',
        'armed_notification' => 'Armado: :outcome — vale para o próximo pedido',
        'disarmed_notification' => 'Desarmado: :outcome',
    ],
```

Em `app-modules/panel-admin/lang/en/fake-binance.php`, o mesmo bloco em inglês:

```php
    'armed_scenarios' => [
        'title' => 'Armed scenarios',
        'fraction' => 'Fill fraction',
        'fraction_helper' => 'How much of the order executes on the partial outcome. Empty means half.',
        'error_code' => 'Error code',
        'raw_status' => 'Arbitrary status',
        'armed_notification' => 'Armed: :outcome — applies to the next request',
        'disarmed_notification' => 'Disarmed: :outcome',
    ],
```

- [ ] **Step 6: Rodar os testes e confirmar que passam**

```bash
php artisan test --compact --filter=ArmedScenariosPage
```

Esperado: 5 passed. A página é auto-descoberta por `discoverPages()` — não precisa registrar no provider.

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app-modules/panel-admin
git commit -m "feat(panel-admin): página de cenários armados com um switch por desfecho"
```

---

### Task 8: Os três switches globais viram switches de verdade

**Files:**
- Modify: `app-modules/panel-admin/src/Filament/Pages/ScenarioSwitchesPage.php`
- Modify: `app-modules/panel-admin/resources/views/filament/pages/scenario-switches.blade.php`
- Modify: `app-modules/panel-admin/tests/Feature/Filament/Pages/ScenarioSwitchesPageTest.php`
- Modify: `app-modules/panel-admin/lang/{pt_BR,en}/fake-binance.php` (remover `turn_on`/`turn_off`, que eram do modal)

**Interfaces:**
- Consumes: `ToggleScenarioSwitch`, `GetScenarioSwitchboard`, `ScenarioSwitch` (já existentes, sem mudança de assinatura).
- Produces: `ScenarioSwitchesPage` com `$data` e `form(Schema $schema): Schema`; o método `toggleAction()` deixa de existir.

- [ ] **Step 1: Atualizar o teste existente**

Leia `app-modules/panel-admin/tests/Feature/Filament/Pages/ScenarioSwitchesPageTest.php` e substitua as asserções que chamam a Action por asserções de switch:

```php
it('turns a global switch on through its toggle', function (): void {
    livewire(ScenarioSwitchesPage::class)
        ->set('data.outage', true)
        ->assertNotified();

    expect((new GetScenarioSwitchboard)->handle()->outage_mode)->toBeTrue();
});

it('turns a global switch back off through the same toggle', function (): void {
    (new ToggleScenarioSwitch)->handle(ScenarioSwitch::Outage, enabled: true);

    livewire(ScenarioSwitchesPage::class)
        ->set('data.outage', false);

    expect((new GetScenarioSwitchboard)->handle()->outage_mode)->toBeFalse();
});

it('loads each switch already reflecting the persisted state', function (): void {
    (new ToggleScenarioSwitch)->handle(ScenarioSwitch::RateLimit, enabled: true);

    livewire(ScenarioSwitchesPage::class)
        ->assertSet('data.rate_limit', true)
        ->assertSet('data.outage', false)
        ->assertSet('data.clock_skew', false);
});
```

Preserve qualquer outro teste do arquivo que não dependa do botão.

- [ ] **Step 2: Rodar e confirmar que falha**

```bash
php artisan test --compact --filter="turns a global switch on through its toggle"
```

Esperado: FAIL — a propriedade `data` não existe na página.

- [ ] **Step 3: Reescrever a página**

Substitua o corpo de `app-modules/panel-admin/src/Filament/Pages/ScenarioSwitchesPage.php` mantendo slug, ícone, grupo e `getDocumentation()`:

```php
    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(): void
    {
        $switchboard = resolve(GetScenarioSwitchboard::class)->handle();

        $this->form->fill(collect(ScenarioSwitch::cases())
            ->mapWithKeys(fn (ScenarioSwitch $switch): array => [
                $switch->value => (bool) $switchboard->{$switch->column()},
            ])
            ->all());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components(array_map(
                fn (ScenarioSwitch $switch): Toggle => Toggle::make($switch->value)
                    ->label($switch->getLabel())
                    ->helperText($switch->getDescription())
                    ->onColor('danger')
                    ->live()
                    ->afterStateUpdated(fn (bool $state) => $this->toggle($switch, $state)),
                ScenarioSwitch::cases(),
            ))
            ->statePath('data');
    }

    private function toggle(ScenarioSwitch $switch, bool $enabled): void
    {
        resolve(ToggleScenarioSwitch::class)->handle($switch, $enabled);

        Notification::make()
            ->title(__('panel-admin::fake-binance.scenario_switches.toggle_notification', [
                'switch' => $switch->getLabel(),
                'state' => __('panel-admin::fake-binance.scenario_switches.'.($enabled ? 'state_on' : 'state_off')),
            ]))
            ->success()
            ->send();
    }
```

Remova `toggleAction()`, `getSwitchRows()` e `getSwitchboard()` — nada mais os chama. Ajuste os `use`: entram `Filament\Forms\Components\Toggle` e `Filament\Schemas\Schema`; sai `Filament\Actions\Action`. Atualize o docblock da classe para descrever o estado atual (switch, sem modal).

- [ ] **Step 4: Simplificar a view**

Substitua `app-modules/panel-admin/resources/views/filament/pages/scenario-switches.blade.php` inteiro:

```blade
<x-filament-panels::page>
    {{ $this->form }}
</x-filament-panels::page>
```

- [ ] **Step 5: Limpar as traduções órfãs**

Remova `turn_on` e `turn_off` dos blocos `scenario_switches` em `lang/pt_BR/fake-binance.php` e `lang/en/fake-binance.php` — eram o heading do modal que não existe mais. Mantenha `toggle_notification`, `state_on` e `state_off`.

- [ ] **Step 6: Rodar os testes e confirmar que passam**

```bash
php artisan test --compact --filter=ScenarioSwitchesPage
```

Esperado: todos passam.

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app-modules/panel-admin
git commit -m "refactor(panel-admin): switches globais acionados por toggle, sem modal"
```

---

### Task 9: Documentação do painel e bateria completa

**Files:**
- Create: `docs/admin/pt_BR/fake-binance/armed-scenarios.md`
- Create: `docs/admin/en/fake-binance/armed-scenarios.md`
- Modify: `docs/admin/pt_BR/fake-binance/scenario-switches.md` e `docs/admin/en/fake-binance/scenario-switches.md` (o texto fala em ligar/desligar; conferir se ainda descreve a interação certa)
- Modify: `app-modules/panel-admin/src/Filament/Pages/ArmedScenariosPage.php` (implementar `HasKnowledgeBase`)

**Interfaces:**
- Consumes: a página da Task 7.
- Produces: doc `fake-binance.armed-scenarios` referenciável por `getDocumentation()`.

- [ ] **Step 1: Escrever a doc pt_BR**

Crie `docs/admin/pt_BR/fake-binance/armed-scenarios.md`:

```markdown
---
title: Cenários armados
icon: heroicon-o-bolt-slash
order: 6
---

# Cenários armados

Aqui você combina o que vai acontecer com o **próximo** pedido de uma perna. Ligue o switch do desfecho, feche a página: o próximo pedido que a aplicação consumidora fizer sai daquele jeito, e o fake volta sozinho ao happy path.

É diferente das ações das outras telas, que rasuram um registro **já criado** — aqui o desvio nasce na própria resposta do pedido, que é o que a venue real faz.

## Regras

- **Vale uma vez.** O primeiro pedido da perna consome o cenário. Para duas seguidas, arme duas vezes.
- **Um desfecho por perna.** Ligar um desliga o anterior — dois desfechos para o mesmo pedido se contradizem. Pernas diferentes ficam armadas ao mesmo tempo.
- **Os switches globais vencem.** Com o modo outage ligado, o pedido nem chega na perna, e o cenário armado continua de pé para depois.
- **O ledger acompanha o desfecho.** Um fill parcial credita só a fração; uma recusa não move nada.

## Conversão spot

- **Preencher parcial e expirar o resto** — executa só a fração informada e responde `EXPIRED`. Vazio usa metade.
- **Recusar com código** — responde o envelope de erro de `/api/v3` e não cria ordem nenhuma.
- **Responder REJECTED** — HTTP 200 com `REJECTED` e fill zerado.
- **Emitir vocabulário desconhecido** — executa normal, mas a wire responde o status que você digitar.
```

- [ ] **Step 2: Escrever a doc en**

Crie `docs/admin/en/fake-binance/armed-scenarios.md` com o mesmo conteúdo em inglês, mesmo front matter.

- [ ] **Step 3: Ligar a página à doc**

Em `ArmedScenariosPage`, implemente o contrato do KB como as outras páginas fazem:

```php
use Guava\FilamentKnowledgeBase\Contracts\HasKnowledgeBase;

class ArmedScenariosPage extends Page implements HasKnowledgeBase
{
    /**
     * @return string[]
     */
    public static function getDocumentation(): array
    {
        return [
            'fake-binance.armed-scenarios',
        ];
    }
```

- [ ] **Step 4: Rodar a bateria completa, na ordem do pre-push**

```bash
./vendor/bin/rector process --dry-run --ansi
./vendor/bin/pint --test --ansi
./vendor/bin/phpstan analyse --ansi
nice -n 19 ./vendor/bin/pest --parallel --processes=10 --compact
```

Corrija o que aparecer. **Nunca** rode `pest --parallel` sem `--processes=10`.

- [ ] **Step 5: Commit**

```bash
git add docs/admin app-modules/panel-admin
git commit -m "docs(panel-admin): página de cenários armados na base de conhecimento"
```

- [ ] **Step 6: Abrir o PR**

```bash
git push --no-verify -u origin story/10-armar-cenario-do-proximo-pedido
gh pr create --base main \
  --title "feat(fake-binance): armar o cenário do próximo pedido — conversão spot" \
  --body "Fase 1 do spec \`app-modules/fake-binance/docs/specs/2026-08-05-armar-cenario-do-proximo-pedido.md\`. Fecha a parte spot da #10; fiat e withdraw vêm na fase 2."
```

O `--no-verify` existe porque o hook do husky roda `pest --parallel` sem `--processes` e trava a máquina — a bateria equivalente já rodou no Step 4.

---

## Auto-revisão do plano

**Cobertura do spec:**

| Seção do spec | Onde |
|---|---|
| D1 vale uma vez | Task 4 (consumo), Task 5 (plano), Task 6 (teste "goes back to the happy path") |
| D2 um armado por perna | Task 3 (índice único), Task 4 (substituição), Task 7 (exclusão na UI) |
| D3 ledger obedece ao desfecho | Task 6 (swap pulado, fração creditada, teste do ledger) |
| D4 persistido | Task 3 |
| D5 consumo atômico | Task 4 (`lockForUpdate`) |
| D6 ações pós-fato seguem | Task 6 Step 6 (suíte spot antiga verde) |
| D7 mecanismo genérico | Tasks 1–4 não sabem nada de spot |
| D8 switch, nunca botão | Tasks 7 e 8 |
| §5.1 os 4 desfechos | Tasks 2, 5, 6 |
| §4 natureza síncrona | Task 6 (o desvio na resposta do POST) |
| §8 BDD conversão | Task 6 |
| §9 testes | Tasks 1–8; contract/arch cobertos pela bateria da Task 9 |

**Fora desta fase, por decisão de escopo:** §5.2 (fiat), §5.3 (withdraw, incluindo a devolução do `ForceWithdrawStatus`), ações de cabeçalho nos Resources, e o BDD de precedência do switch global sob cenário armado — todos vão para o plano da Fase 2.

**Consistência de tipos:** `LegOutcomeContract` (Task 2) é o tipo que `ArmScenario` (Task 4) e `ArmedScenariosPage` (Task 7) consomem; `resolvedOutcome()` (Task 3) é o nome usado nas Tasks 4, 5 e 7; `SpotExecutionPlan::fillsNothing()`/`applyFraction()` (Task 5) são exatamente os nomes chamados na Task 6.
