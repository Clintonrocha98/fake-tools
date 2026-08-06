<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Invoice\Models;

use App\Models\BaseModel;
use Carbon\CarbonImmutable;
use He4rt\FakeStarkbank\Database\Factories\Invoice\InvoiceFactory;
use He4rt\FakeStarkbank\Invoice\Enums\InvoiceStatus;
use He4rt\FakeStarkbank\Support\Casts\AsWireTags;
use He4rt\FakeStarkbank\Support\NumericId;
use He4rt\FakeStarkbank\Support\WireTags;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Uma cobrança PIX emitida (a perna de cash-in). Nasce em
 * {@see InvoiceStatus::Created} pelo `POST /v2/invoice` e depois só avança na
 * LEITURA, por idade — nunca por scheduler.
 *
 * `destined_status`, `extra_advance_seconds` e `frozen` são o destino que um
 * cenário armado gravou na criação: a partir daí a linha se basta, e nenhuma
 * leitura volta à tabela de cenários.
 *
 * @property string $id
 * @property int $amount
 * @property string $name
 * @property string $tax_id
 * @property InvoiceStatus $status
 * @property string $brcode
 * @property WireTags $tags
 * @property Carbon $due
 * @property int $expiration
 * @property Carbon|null $paid_at
 * @property Carbon|null $expired_at
 * @property bool $frozen
 * @property InvoiceStatus|null $destined_status
 * @property int $extra_advance_seconds
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @extends BaseModel<InvoiceFactory>
 */
#[UseFactory(factoryClass: InvoiceFactory::class)]
#[Table(name: 'fake_starkbank_invoices')]
final class Invoice extends BaseModel
{
    /**
     * A pk é o id do StarkBank, não um uuid: é ele que viaja na wire, no
     * brcode e na URL do PDF.
     */
    public function newUniqueId(): string
    {
        return NumericId::generate();
    }

    /**
     * O instante em que a invoice deixa de aceitar pagamento: o vencimento
     * mais os segundos de graça. Com `expiration` zerado, `due` é prazo duro.
     */
    public function graceEndsAt(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->due)->addSeconds($this->expiration);
    }

    /**
     * Sem trilha de activity log: o `subject_id` daquela tabela é uuid e o id
     * desta invoice é o id numérico do StarkBank, então cada gravação viraria
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
     * `created_at` sozinho empata entre invoices emitidas no mesmo instante; o
     * `id` desempata e torna a página determinística.
     *
     * @param  Builder<Invoice>  $query
     */
    protected function scopeInPageOrder(Builder $query): void
    {
        $query->latest()->orderByDesc('id');
    }

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'status' => InvoiceStatus::class,
            'tags' => AsWireTags::class,
            'due' => 'datetime',
            'expiration' => 'integer',
            'paid_at' => 'datetime',
            'expired_at' => 'datetime',
            'frozen' => 'boolean',
            'destined_status' => InvoiceStatus::class,
            'extra_advance_seconds' => 'integer',
        ];
    }
}
