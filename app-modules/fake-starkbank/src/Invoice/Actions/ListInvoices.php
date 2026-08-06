<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Invoice\Actions;

use Carbon\CarbonImmutable;
use He4rt\FakeStarkbank\Invoice\DTOs\InvoiceListPage;
use He4rt\FakeStarkbank\Invoice\DTOs\InvoiceView;
use He4rt\FakeStarkbank\Invoice\Enums\InvoiceStatus;
use He4rt\FakeStarkbank\Invoice\Models\Invoice;
use He4rt\FakeStarkbank\Support\PageCursor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * `GET /v2/invoice?status=paid&after={cursor}` — o extrato que a rede de
 * segurança da conciliação varre.
 *
 * Três decisões governam a página:
 *
 *   - **a página 1 é a janela RECENTE** (mais novo primeiro, ADR-0002). O
 *     `poll-extrato` manda um request só e não segue o cursor: com o extrato
 *     invertido, tudo que ele lê acima de 100 linhas é arquivo morto;
 *   - **o avanço lazy roda ANTES do filtro de status**, sobre a janela lida.
 *     Filtrar `status=paid` no SQL esconderia justamente a invoice que
 *     amadureceu nesta leitura, e o ciclo emitir → avançar → varrer nunca
 *     fecharia sozinho;
 *   - por isso uma página pode vir com menos de {@see PAGE_SIZE} itens sem
 *     significar fim: quem encerra a paginação é `cursor: null`, nunca a
 *     contagem.
 *
 * O parâmetro `after` carrega duas coisas na prática — o cursor opaco desta
 * mesma listagem e a data ISO-8601 que `starkbank:poll-extrato --after=` passa
 * na primeira página. Os dois são aceitos; um valor que não é nenhum dos dois
 * é ignorado com aviso, porque servir zero linhas por um cursor ilegível
 * parece "extrato vazio" e esconde o defeito.
 */
final readonly class ListInvoices
{
    /**
     * Teto de página da doc oficial do StarkBank — fixo, não configurável: o
     * consumidor pagina até `cursor: null` e nunca pede um tamanho.
     */
    public const int PAGE_SIZE = 100;

    public function __construct(private AdvanceInvoiceStatus $advance) {}

    public function handle(?InvoiceStatus $status = null, ?string $after = null): InvoiceListPage
    {
        $query = Invoice::query()->inPageOrder();

        $this->applyAfter($query, $after);

        // Uma linha além da página revela se existe próxima sem uma segunda
        // consulta de contagem.
        $window = $query->limit(self::PAGE_SIZE + 1)->get();
        $hasMore = $window->count() > self::PAGE_SIZE;

        /** @var Collection<int, Invoice> $page */
        $page = $window->take(self::PAGE_SIZE);

        $advanced = $page->map(fn (Invoice $invoice): Invoice => $this->advance->handle($invoice));

        $invoices = array_values(
            $advanced
                ->filter(fn (Invoice $invoice): bool => !$status instanceof InvoiceStatus || $invoice->status === $status)
                ->map(fn (Invoice $invoice): InvoiceView => InvoiceView::fromModel($invoice))
                ->all()
        );

        $cursor = $this->nextCursor($page->last(), $hasMore);

        Log::info('fake-starkbank.invoice: extrato servido com o avanço lazy aplicado antes do filtro — é o que faz uma invoice madurada nesta leitura já aparecer em status=paid', [
            'status' => $status?->value,
            'window' => $page->count(),
            'servidas' => count($invoices),
            'tem_proxima_pagina' => $hasMore,
        ]);

        return new InvoiceListPage($invoices, $cursor);
    }

    /**
     * @param  Builder<Invoice>  $query
     */
    private function applyAfter(Builder $query, ?string $after): void
    {
        if ($after === null || $after === '') {
            return;
        }

        $cursor = PageCursor::tryDecode($after);

        if ($cursor instanceof PageCursor) {
            // Keyset sobre o par que ordena a página, caminhando para TRÁS no
            // tempo com ela (ADR-0002): linhas novas chegando no meio da
            // varredura não deslocam a página seguinte, como um OFFSET
            // deslocaria.
            $query->where(
                /** @param Builder<Invoice> $scoped */
                function (Builder $scoped) use ($cursor): void {
                    $scoped->where('created_at', '<', $cursor->createdAt)
                        ->orWhere(
                            /** @param Builder<Invoice> $tie */
                            function (Builder $tie) use ($cursor): void {
                                $tie->where('created_at', '=', $cursor->createdAt)
                                    ->where('id', '<', $cursor->id);
                            }
                        );
                }
            );

            return;
        }

        $since = $this->tryDate($after);

        if ($since instanceof CarbonImmutable) {
            // Data é FILTRO, não sentido de leitura: recorta a janela e deixa a
            // ordem de sempre decidir por onde ela começa.
            $query->where('created_at', '>=', $since);

            return;
        }

        Log::warning('fake-starkbank.invoice: `after` ignorado — não é um cursor desta listagem nem uma data ISO-8601, e servir zero linhas passaria por extrato vazio', [
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

    private function nextCursor(?Invoice $last, bool $hasMore): ?string
    {
        if (!$hasMore || !$last instanceof Invoice) {
            return null;
        }

        // O cursor aponta para a última linha da JANELA, não da página servida:
        // o filtro de status pode ter descartado essa linha, e ancorar no
        // último item servido faria a paginação repetir o que já passou.
        return PageCursor::at(CarbonImmutable::parse($last->created_at), $last->id)->encode();
    }
}
