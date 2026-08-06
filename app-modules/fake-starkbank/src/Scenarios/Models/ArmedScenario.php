<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Scenarios\Models;

use App\Models\BaseModel;
use He4rt\FakeStarkbank\Database\Factories\Scenarios\ArmedScenarioFactory;
use He4rt\FakeStarkbank\Scenarios\Casts\AsPixScenarioPayload;
use He4rt\FakeStarkbank\Scenarios\Contracts\PixLegOutcomeContract;
use He4rt\FakeStarkbank\Scenarios\DTOs\PixScenarioPayload;
use He4rt\FakeStarkbank\Scenarios\Enums\PixLeg;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Support\Carbon;

/**
 * O cenário combinado para o PRÓXIMO pedido (ou evento) de uma perna da malha
 * PIX. Vale uma vez: quem executa consome a linha
 * ({@see \He4rt\FakeStarkbank\Scenarios\Actions\ConsumeArmedScenario}), e o
 * pedido seguinte volta ao happy path. Persistido em banco porque o fake roda
 * em container — um flag em memória morreria no restart.
 *
 * @property string $id
 * @property PixLeg $leg
 * @property string $outcome
 * @property PixScenarioPayload $payload
 * @property Carbon $armed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @extends BaseModel<ArmedScenarioFactory>
 */
#[UseFactory(factoryClass: ArmedScenarioFactory::class)]
#[Table(name: 'fake_starkbank_armed_scenarios')]
final class ArmedScenario extends BaseModel
{
    /**
     * Nome deliberadamente diferente da coluna `outcome`: um método público
     * homônimo de um atributo é lido por `isRelation()` como relação e explode
     * no primeiro acesso ao atributo.
     *
     * `null` quando a coluna guarda um desfecho que a perna não conhece mais
     * ({@see PixLeg::outcomeFrom()}) — quem chama trata como "nada armado".
     */
    public function resolvedOutcome(): ?PixLegOutcomeContract
    {
        return $this->leg->outcomeFrom($this->outcome);
    }

    protected function casts(): array
    {
        return [
            'leg' => PixLeg::class,
            'payload' => AsPixScenarioPayload::class,
            'armed_at' => 'datetime',
        ];
    }
}
