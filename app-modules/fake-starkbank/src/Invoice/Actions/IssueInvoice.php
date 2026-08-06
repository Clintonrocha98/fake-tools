<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Invoice\Actions;

use He4rt\FakeStarkbank\Invoice\DTOs\InvoiceView;
use He4rt\FakeStarkbank\Invoice\DTOs\IssueInvoiceData;
use He4rt\FakeStarkbank\Invoice\Enums\InvoiceStatus;
use He4rt\FakeStarkbank\Invoice\Models\Invoice;
use He4rt\FakeStarkbank\Invoice\Support\SyntheticBrcode;
use He4rt\FakeStarkbank\Support\NumericId;
use He4rt\FakeStarkbank\Webhook\Contracts\EmitsWebhookEvents;
use He4rt\FakeStarkbank\Webhook\Enums\StarkbankSubscription;
use Illuminate\Support\Facades\Log;

/**
 * `POST /v2/invoice` — emite uma cobrança em {@see InvoiceStatus::Created} com
 * o brcode já anexado e anuncia o evento `created`.
 *
 * O id é gerado ANTES do insert porque o brcode, o link e o PDF o embutem: uma
 * invoice cujo brcode aponte para outro id não existe no StarkBank real.
 */
final readonly class IssueInvoice
{
    public function __construct(private EmitsWebhookEvents $webhooks) {}

    public function handle(IssueInvoiceData $data): Invoice
    {
        $id = NumericId::generate();

        $invoice = Invoice::query()->create([
            'id' => $id,
            'amount' => $data->amount,
            'name' => $data->name,
            'tax_id' => $data->taxId,
            'status' => InvoiceStatus::Created,
            'brcode' => SyntheticBrcode::forInvoice($id, $data->amount, $data->name),
            'tags' => $data->tags,
            'due' => $data->due,
            'expiration' => $data->expiration,
        ]);

        Log::info('fake-starkbank.invoice: cobrança emitida com brcode imediato — é o pagamento humano do QR que fecha o cash-in, então a invoice já nasce pagável', [
            'invoice_id' => $invoice->id,
            'amount' => $invoice->amount,
            'correlation_id' => $invoice->tags->correlationId(),
            'due' => $invoice->due->toIso8601String(),
            'expiration' => $invoice->expiration,
        ]);

        $this->webhooks->emit(
            StarkbankSubscription::Invoice->value,
            InvoiceStatus::Created->eventType()->value,
            InvoiceView::fromModel($invoice)->jsonSerialize(),
        );

        return $invoice;
    }
}
