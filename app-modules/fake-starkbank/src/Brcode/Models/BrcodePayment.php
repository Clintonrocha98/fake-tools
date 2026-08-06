<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Brcode\Models;

use App\Models\BaseModel;
use He4rt\FakeStarkbank\Brcode\Enums\BrcodePaymentStatus;
use He4rt\FakeStarkbank\Database\Factories\Brcode\BrcodePaymentFactory;
use He4rt\FakeStarkbank\Support\Casts\AsWireTags;
use He4rt\FakeStarkbank\Support\NumericId;
use He4rt\FakeStarkbank\Support\WireTags;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * O pagamento de um BR Code de terceiro — a perna de funding da venue. Nasce em
 * {@see BrcodePaymentStatus::Created} pelo `POST /v2/brcode-payment` e depois só
 * avança na LEITURA, por idade — nunca por scheduler.
 *
 * @property string $id
 * @property string $brcode
 * @property string $tax_id
 * @property int $amount
 * @property BrcodePaymentStatus $status
 * @property string|null $description
 * @property WireTags $tags
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @extends BaseModel<BrcodePaymentFactory>
 */
#[UseFactory(factoryClass: BrcodePaymentFactory::class)]
#[Table(name: 'fake_starkbank_brcode_payments')]
final class BrcodePayment extends BaseModel
{
    /**
     * A pk é o id do StarkBank, não um uuid: é ele o providerRef que o
     * consumidor sela na Conversion e devolve no
     * `GET /v2/brcode-payment/{id}`.
     */
    public function newUniqueId(): string
    {
        return NumericId::generate();
    }

    /**
     * Sem trilha de activity log: o `subject_id` daquela tabela é uuid e o id
     * deste pagamento é o id numérico do StarkBank, então cada gravação viraria
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
     * paginação codifica. `created_at` sozinho empata entre pagamentos criados
     * no mesmo instante; o `id` desempata e torna a página determinística.
     *
     * @param  Builder<BrcodePayment>  $query
     */
    protected function scopeInPageOrder(Builder $query): void
    {
        $query->oldest()->orderBy('id');
    }

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'status' => BrcodePaymentStatus::class,
            'tags' => AsWireTags::class,
        ];
    }
}
