<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Invoice\Actions;

use Carbon\CarbonImmutable;
use He4rt\FakeStarkbank\Invoice\DTOs\InvoiceView;
use He4rt\FakeStarkbank\Invoice\Enums\InvoiceStatus;
use He4rt\FakeStarkbank\Invoice\Models\Invoice;
use He4rt\FakeStarkbank\Webhook\Contracts\EmitsWebhookEvents;
use He4rt\FakeStarkbank\Webhook\Enums\StarkbankSubscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Avanço automático LAZY do ciclo de vida da invoice: só roda quando a invoice
 * é LIDA (GET single ou listagem), nunca por scheduler.
 *
 * Dois relógios, nesta ordem de precedência:
 *
 *   1. o do documento — `due` e os segundos de graça (`expiration`) — que leva
 *      a `overdue` e depois a `expired`;
 *   2. o do pagamento simulado — `fake-starkbank-invoice.advance_seconds`
 *      desde a criação — que leva a `paid`.
 *
 * O vencimento vem primeiro porque é do próprio documento: uma invoice lida
 * depois de vencida nunca "paga atrasado". Uma leitura muito posterior salta
 * direto ao estado final que os relógios já alcançaram e emite só o evento
 * desse estado — o StarkBank real emitiria a série inteira, mas eventos de
 * estados intermediários que ninguém observou não mudam nada do lado do
 * consumidor, que relê por GET.
 */
final readonly class AdvanceInvoiceStatus
{
    public function __construct(private EmitsWebhookEvents $webhooks) {}

    public function handle(Invoice $invoice): Invoice
    {
        if (!$invoice->status->advancesAutomatically()) {
            return $invoice;
        }

        if ($invoice->frozen) {
            Log::debug('fake-starkbank.invoice: avanço lazy pulado — invoice congelada por cenário, o estado fica exatamente onde o operador o deixou', [
                'invoice_id' => $invoice->id,
                'status' => $invoice->status->value,
            ]);

            return $invoice;
        }

        // A checagem fora do lock é o caminho comum de uma varredura de extrato:
        // sem ela, cada linha lida abriria uma transação e um SELECT FOR UPDATE
        // só para descobrir que nada mudou.
        if (!$this->targetStatus($invoice) instanceof InvoiceStatus) {
            return $invoice;
        }

        $advanced = DB::transaction(function () use ($invoice): ?Invoice {
            // Duas leituras concorrentes disputam o mesmo avanço; o lock decide
            // quem transita, e quem perde relê o estado já avançado e não
            // reemite o evento.
            $locked = Invoice::query()->whereKey($invoice->getKey())->lockForUpdate()->first();

            if (!$locked instanceof Invoice || !$locked->status->advancesAutomatically() || $locked->frozen) {
                return null;
            }

            $target = $this->targetStatus($locked);

            if (!$target instanceof InvoiceStatus) {
                return null;
            }

            $locked->update($this->transitionAttributes($target));

            return $locked->refresh();
        });

        if (!$advanced instanceof Invoice) {
            return $invoice;
        }

        Log::info('fake-starkbank.invoice: status avançado na leitura — o fake não tem scheduler, então é o próprio GET do consumidor que faz o tempo passar', [
            'invoice_id' => $advanced->id,
            'from' => $invoice->status->value,
            'to' => $advanced->status->value,
            'correlation_id' => $advanced->tags->correlationId(),
        ]);

        $this->webhooks->emit(
            StarkbankSubscription::Invoice->value,
            $advanced->status->eventType()->value,
            InvoiceView::fromModel($advanced)->jsonSerialize(),
        );

        return $advanced;
    }

    private function targetStatus(Invoice $invoice): ?InvoiceStatus
    {
        $now = CarbonImmutable::now();

        if ($now->greaterThanOrEqualTo($invoice->graceEndsAt())) {
            return InvoiceStatus::Expired;
        }

        if ($now->greaterThanOrEqualTo(CarbonImmutable::parse($invoice->due))) {
            return $invoice->status === InvoiceStatus::Overdue ? null : InvoiceStatus::Overdue;
        }

        if ($invoice->status !== InvoiceStatus::Created) {
            return null;
        }

        $advanceSeconds = $this->advanceSeconds();

        if ($advanceSeconds <= 0) {
            return null;
        }

        $age = $invoice->created_at?->diffInSeconds($now) ?? 0.0;

        return $age >= $advanceSeconds ? InvoiceStatus::Paid : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function transitionAttributes(InvoiceStatus $target): array
    {
        return match ($target) {
            InvoiceStatus::Paid => ['status' => $target, 'paid_at' => CarbonImmutable::now()],
            InvoiceStatus::Expired => ['status' => $target, 'expired_at' => CarbonImmutable::now()],
            InvoiceStatus::Created, InvoiceStatus::Credited, InvoiceStatus::Overdue,
            InvoiceStatus::Canceled, InvoiceStatus::Reversed => ['status' => $target],
        };
    }

    /**
     * `config()->integer()` exigiria um int estrito e explodiria na
     * numeric-string que `env()` produz de um `.env` real.
     */
    private function advanceSeconds(): int
    {
        return (int) config('fake-starkbank-invoice.advance_seconds', 60);
    }
}
