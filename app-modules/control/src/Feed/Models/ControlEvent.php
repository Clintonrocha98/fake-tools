<?php

declare(strict_types=1);

namespace He4rt\Control\Feed\Models;

use Carbon\CarbonImmutable;
use He4rt\Control\Database\Factories\Feed\ControlEventFactory;
use He4rt\Control\Feed\Casts\AsControlEventContext;
use He4rt\Control\Feed\DTOs\ControlEventContext;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Support\Facades\Date;

/**
 * Uma linha dos canais `binance`/`starkbank` persistida para o feed. Não estende
 * `App\Models\BaseModel` de propósito: a base traz `HasUuids` (e o `id` aqui é o
 * cursor bigserial) e `HasActivity` (que faria cada evento de telemetria gerar
 * outra linha de auditoria).
 *
 * @property int $id
 * @property string $channel
 * @property string $level
 * @property string $message
 * @property ControlEventContext $context
 * @property string|null $request_id
 * @property CarbonImmutable $occurred_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[UseFactory(factoryClass: ControlEventFactory::class)]
#[Table(name: 'control_events')]
final class ControlEvent extends Model
{
    /** @use HasFactory<ControlEventFactory> */
    use HasFactory;

    use Prunable;

    protected $guarded = [];

    /**
     * Escrita e leitura passam pela conexão dedicada — a mesma que o handler
     * usa para ficar fora da transação de negócio
     * ({@see \He4rt\Control\Feed\ControlEventHandler}).
     */
    public function getConnectionName(): string
    {
        return config()->string('control.connection');
    }

    /**
     * Telemetria de dev, não auditoria: prune por idade. O `model:prune` do
     * scheduler cobre o resto.
     *
     * @return Builder<ControlEvent>
     */
    public function prunable(): Builder
    {
        $hours = config()->integer('control.feed.retention_hours');

        return self::query()->where('occurred_at', '<', Date::now()->subHours($hours));
    }

    /**
     * A ordem canônica do feed: CRESCENTE por `id`. Contrasta de propósito com
     * o extrato do fake-starkbank (do mais novo para o mais antigo, ADR-0002) —
     * um feed por cursor precisa ser append-only na direção do cursor, senão a
     * sidebar não emenda página com página.
     *
     * @param  Builder<ControlEvent>  $query
     */
    protected function scopeInFeedOrder(Builder $query): void
    {
        $query->orderBy('id');
    }

    /**
     * @param  Builder<ControlEvent>  $query
     */
    protected function scopeAfterCursor(Builder $query, ?int $after): void
    {
        $query->when($after !== null, fn (Builder $query): Builder => $query->where('id', '>', $after));
    }

    protected function casts(): array
    {
        return [
            'context' => AsControlEventContext::class,
            'occurred_at' => 'immutable_datetime',
        ];
    }
}
