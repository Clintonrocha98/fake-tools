<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Invoice\Actions;

use Carbon\CarbonImmutable;
use He4rt\FakeStarkbank\Invoice\DTOs\InvoiceView;
use He4rt\FakeStarkbank\Invoice\Enums\InvoiceStatus;
use He4rt\FakeStarkbank\Invoice\Models\Invoice;
use He4rt\FakeStarkbank\Webhook\Contracts\EmitsWebhookEvents;
use He4rt\FakeStarkbank\Webhook\Enums\StarkbankSubscription;
use Illuminate\Support\Facades\Log;

/**
 * Cenário: move a invoice para qualquer estado do vocabulário IGNORANDO os dois
 * relógios, e emite o evento correspondente.
 *
 * Ao contrário do override de leitura do fake-binance, aqui o status forçado é
 * GRAVADO: `canceled`, `expired` e `reversed` são desfechos definitivos no
 * StarkBank real, e uma máscara que o avanço lazy pudesse desfazer produziria
 * uma invoice que "descancela" na leitura seguinte. Quem quer segurar um estado
 * que ainda avançaria (`created`, `overdue`) usa {@see SetInvoiceFrozen}.
 */
final readonly class ForceInvoiceStatus
{
    public function __construct(private EmitsWebhookEvents $webhooks) {}

    public function handle(Invoice $invoice, InvoiceStatus $status): Invoice
    {
        $from = $invoice->status;

        $invoice->update($this->transitionAttributes($status));
        $invoice->refresh();

        Log::info('fake-starkbank.invoice: status forçado por cenário — o relógio é ignorado de propósito, para exercitar um ramo que o consumidor trata mas raramente vê', [
            'invoice_id' => $invoice->id,
            'from' => $from->value,
            'to' => $status->value,
            'correlation_id' => $invoice->tags->correlationId(),
        ]);

        $this->webhooks->emit(
            StarkbankSubscription::Invoice->value,
            $status->eventType()->value,
            InvoiceView::fromModel($invoice)->jsonSerialize(),
        );

        return $invoice;
    }

    /**
     * @return array<string, mixed>
     */
    private function transitionAttributes(InvoiceStatus $status): array
    {
        return match ($status) {
            InvoiceStatus::Paid => ['status' => $status, 'paid_at' => CarbonImmutable::now()],
            InvoiceStatus::Expired => ['status' => $status, 'expired_at' => CarbonImmutable::now()],
            InvoiceStatus::Created, InvoiceStatus::Credited, InvoiceStatus::Overdue,
            InvoiceStatus::Canceled, InvoiceStatus::Reversed => ['status' => $status],
        };
    }
}
