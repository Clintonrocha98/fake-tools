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
     *
     * `null` quando a coluna guarda um desfecho que a perna não conhece mais
     * ({@see VenueLeg::outcomeFrom()}) — quem chama trata como "nada armado".
     */
    public function resolvedOutcome(): ?LegOutcomeContract
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
