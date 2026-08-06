<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Transfer\Actions;

use Carbon\CarbonImmutable;
use He4rt\FakeStarkbank\Support\PageCursor;
use He4rt\FakeStarkbank\Transfer\DTOs\TransferListPage;
use He4rt\FakeStarkbank\Transfer\DTOs\TransferView;
use He4rt\FakeStarkbank\Transfer\Enums\TransferStatus;
use He4rt\FakeStarkbank\Transfer\Models\Transfer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * `GET /v2/transfer?status=success&after={cursor}` — o extrato de cash-out que
 * a rede de segurança da conciliação varre.
 *
 * Duas decisões governam a página, as mesmas da listagem de invoice:
 *
 *   - **o avanço lazy roda ANTES do filtro de status**, sobre a janela lida.
 *     Filtrar `status=success` no SQL esconderia justamente a transfer que
 *     liquidou nesta leitura, e o ciclo despachar → avançar → varrer nunca
 *     fecharia sozinho;
 *   - por isso uma página pode vir com menos de {@see PAGE_SIZE} itens sem
 *     significar fim: quem encerra a paginação é `cursor: null`, nunca a
 *     contagem.
 *
 * O parâmetro `after` carrega duas coisas na prática — o cursor opaco desta
 * mesma listagem e a data ISO-8601 que `starkbank:poll-extrato --after=` passa
 * na primeira página. Os dois são aceitos; um valor que não é nenhum dos dois é
 * ignorado com aviso, porque servir zero linhas por um cursor ilegível parece
 * "extrato vazio" e esconde o defeito.
 */
final readonly class ListTransfers
{
    /**
     * Teto de página da doc oficial do StarkBank — fixo, não configurável: o
     * consumidor pagina até `cursor: null` e nunca pede um tamanho.
     */
    public const int PAGE_SIZE = 100;

    public function __construct(private AdvanceTransferStatus $advance) {}

    public function handle(?TransferStatus $status = null, ?string $after = null): TransferListPage
    {
        $query = Transfer::query()->inPageOrder();

        $this->applyAfter($query, $after);

        // Uma linha além da página revela se existe próxima sem uma segunda
        // consulta de contagem.
        $window = $query->limit(self::PAGE_SIZE + 1)->get();
        $hasMore = $window->count() > self::PAGE_SIZE;

        /** @var Collection<int, Transfer> $page */
        $page = $window->take(self::PAGE_SIZE);

        $advanced = $page->map(fn (Transfer $transfer): Transfer => $this->advance->handle($transfer));

        $transfers = array_values(
            $advanced
                ->filter(fn (Transfer $transfer): bool => !$status instanceof TransferStatus || $transfer->status === $status)
                ->map(fn (Transfer $transfer): TransferView => TransferView::fromModel($transfer))
                ->all()
        );

        $cursor = $this->nextCursor($page->last(), $hasMore);

        Log::info('fake-starkbank.transfer: extrato servido com o avanço lazy aplicado antes do filtro — é o que faz uma transfer liquidada nesta leitura já aparecer em status=success', [
            'status' => $status?->value,
            'window' => $page->count(),
            'servidas' => count($transfers),
            'tem_proxima_pagina' => $hasMore,
        ]);

        return new TransferListPage($transfers, $cursor);
    }

    /**
     * @param  Builder<Transfer>  $query
     */
    private function applyAfter(Builder $query, ?string $after): void
    {
        if ($after === null || $after === '') {
            return;
        }

        $cursor = PageCursor::tryDecode($after);

        if ($cursor instanceof PageCursor) {
            // Keyset sobre o par que ordena a página: linhas novas chegando no
            // meio da varredura não deslocam a página seguinte, como um OFFSET
            // deslocaria.
            $query->where(
                /** @param Builder<Transfer> $scoped */
                function (Builder $scoped) use ($cursor): void {
                    $scoped->where('created_at', '>', $cursor->createdAt)
                        ->orWhere(
                            /** @param Builder<Transfer> $tie */
                            function (Builder $tie) use ($cursor): void {
                                $tie->where('created_at', '=', $cursor->createdAt)
                                    ->where('id', '>', $cursor->id);
                            }
                        );
                }
            );

            return;
        }

        $since = $this->tryDate($after);

        if ($since instanceof CarbonImmutable) {
            $query->where('created_at', '>=', $since);

            return;
        }

        Log::warning('fake-starkbank.transfer: `after` ignorado — não é um cursor desta listagem nem uma data ISO-8601, e servir zero linhas passaria por extrato vazio', [
            'after' => $after,
        ]);
    }

    private function tryDate(string $after): ?CarbonImmutable
    {
        try {
            return CarbonImmutable::parse($after);
        } catch (Throwable) {
            return null;
        }
    }

    private function nextCursor(?Transfer $last, bool $hasMore): ?string
    {
        if (!$hasMore || !$last instanceof Transfer) {
            return null;
        }

        // O cursor aponta para a última linha da JANELA, não da página servida:
        // o filtro de status pode ter descartado essa linha, e ancorar no
        // último item servido faria a paginação repetir o que já passou.
        return PageCursor::at(CarbonImmutable::parse($last->created_at), $last->id)->encode();
    }
}
