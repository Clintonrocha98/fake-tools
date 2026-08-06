<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Transfer\Models;

use App\Models\BaseModel;
use He4rt\FakeStarkbank\Database\Factories\Transfer\TransferFactory;
use He4rt\FakeStarkbank\Support\Casts\AsWireTags;
use He4rt\FakeStarkbank\Support\NumericId;
use He4rt\FakeStarkbank\Support\WireTags;
use He4rt\FakeStarkbank\Transfer\Enums\TransferStatus;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Um PIX de saída (a perna de cash-out). Nasce em
 * {@see TransferStatus::Created} pelo `POST /v2/transfer` e depois só avança na
 * LEITURA, por idade — nunca por scheduler.
 *
 * `destined_status`, `failure_reason` e `held` são o destino que um cenário
 * armado gravou na criação: a partir daí a linha se basta, e nenhuma leitura
 * volta à tabela de cenários.
 *
 * @property string $id
 * @property int $amount
 * @property string $name
 * @property string $tax_id
 * @property string $bank_code
 * @property string $branch_code
 * @property string $account_number
 * @property string $account_type
 * @property string|null $external_id
 * @property TransferStatus $status
 * @property TransferStatus|null $destined_status
 * @property string|null $failure_reason
 * @property bool $held
 * @property WireTags $tags
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @extends BaseModel<TransferFactory>
 */
#[UseFactory(factoryClass: TransferFactory::class)]
#[Table(name: 'fake_starkbank_transfers')]
final class Transfer extends BaseModel
{
    /**
     * A pk é o id do StarkBank, não um uuid: é ele o providerRef que o
     * consumidor sela no Payout e devolve no `GET /v2/transfer/{id}`.
     */
    public function newUniqueId(): string
    {
        return NumericId::generate();
    }

    /**
     * Sem trilha de activity log: o `subject_id` daquela tabela é uuid e o id
     * desta transfer é o id numérico do StarkBank, então cada gravação viraria
     * um insert que sempre falha. A trilha desta perna é a fila de emissões de
     * webhook, que já registra toda transição de estado.
     *
     * @return Collection<int, string>
     */
    protected static function eventsToBeRecorded(): Collection
    {
        return new Collection;
    }

    /**
     * A ordem canônica de toda listagem do módulo — a mesma que o cursor de
     * paginação codifica: do mais NOVO para o mais antigo (ADR-0002).
     * `created_at` sozinho empata entre transfers despachadas no mesmo
     * instante; o `id` desempata e torna a página determinística.
     *
     * @param  Builder<Transfer>  $query
     */
    protected function scopeInPageOrder(Builder $query): void
    {
        $query->latest()->orderByDesc('id');
    }

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'status' => TransferStatus::class,
            'destined_status' => TransferStatus::class,
            'held' => 'boolean',
            'tags' => AsWireTags::class,
        ];
    }
}
