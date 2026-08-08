<?php

declare(strict_types=1);

namespace He4rt\Control\State\Support;

use He4rt\Control\State\DTOs\ResourceRowView;
use He4rt\FakeBinance\Deposit\Models\CryptoDeposit;
use He4rt\FakeBinance\Fiat\Models\FiatOrder;
use He4rt\FakeBinance\Ledger\Models\LedgerAccount;
use He4rt\FakeBinance\Spot\Models\SpotOrder;
use He4rt\FakeBinance\Withdraw\Models\Withdrawal;
use He4rt\FakeStarkbank\Brcode\Models\BrcodePayment;
use He4rt\FakeStarkbank\Dict\Models\DictEntry;
use He4rt\FakeStarkbank\Invoice\Models\Invoice;
use He4rt\FakeStarkbank\Transfer\Models\Transfer;
use He4rt\FakeStarkbank\Webhook\Models\WebhookEmission;

/**
 * Como cada recurso dos dois fakes aparece no plano de controle — a ÚNICA
 * serialização, usada tanto pelo snapshot quanto pela resposta de cada comando.
 * Duas serializações do mesmo recurso divergiriam no primeiro campo novo, e o
 * dev leria dois retratos diferentes da mesma linha.
 */
final readonly class ResourceRows
{
    public static function invoice(Invoice $invoice): ResourceRowView
    {
        $advanceSeconds = AdvanceClock::seconds('fake-starkbank-invoice.advance_seconds');

        return new ResourceRowView(
            id: $invoice->id,
            status: $invoice->status->value,
            attributes: [
                'amount' => $invoice->amount,
                'taxId' => $invoice->tax_id,
                'destinedStatus' => $invoice->destined_status?->value,
                'frozen' => $invoice->frozen,
                'extraAdvanceSeconds' => $invoice->extra_advance_seconds,
                'due' => $invoice->due->toIso8601String(),
            ],
            advance: AdvanceClock::project(
                $invoice->created_at,
                $advanceSeconds + $invoice->extra_advance_seconds,
                $invoice->frozen ? 'frozen' : null,
            ),
            createdAt: $invoice->created_at?->toIso8601String(),
        );
    }

    public static function transfer(Transfer $transfer): ResourceRowView
    {
        return new ResourceRowView(
            id: $transfer->id,
            status: $transfer->status->value,
            attributes: [
                'amount' => $transfer->amount,
                'taxId' => $transfer->tax_id,
                'externalId' => $transfer->external_id,
                'destinedStatus' => $transfer->destined_status?->value,
                'failureReason' => $transfer->failure_reason,
                'held' => $transfer->held,
            ],
            advance: AdvanceClock::project(
                $transfer->created_at,
                AdvanceClock::seconds('fake-starkbank-transfer.advance_seconds'),
                $transfer->held ? 'held' : null,
            ),
            createdAt: $transfer->created_at?->toIso8601String(),
        );
    }

    public static function brcodePayment(BrcodePayment $payment): ResourceRowView
    {
        return new ResourceRowView(
            id: $payment->id,
            status: $payment->status->value,
            attributes: [
                'amount' => $payment->amount,
                'taxId' => $payment->tax_id,
                'destinedStatus' => $payment->destined_status?->value,
                'failureReason' => $payment->failure_reason,
                'held' => $payment->held,
            ],
            advance: AdvanceClock::project(
                $payment->created_at,
                AdvanceClock::seconds('fake-starkbank-brcode.advance_seconds'),
                $payment->held ? 'held' : null,
            ),
            createdAt: $payment->created_at?->toIso8601String(),
        );
    }

    public static function webhookEmission(WebhookEmission $emissao): ResourceRowView
    {
        return new ResourceRowView(
            id: $emissao->id,
            status: match (true) {
                $emissao->wasDelivered() => 'delivered',
                $emissao->isHeld() => 'held',
                default => 'pending',
            },
            attributes: [
                'eventId' => $emissao->event_id,
                'subscription' => $emissao->subscription->value,
                'eventType' => $emissao->event_type->value,
                'entityId' => $emissao->entity_id,
                'responseCode' => $emissao->response_code,
                'sentAt' => $emissao->sent_at?->toIso8601String(),
                'heldAt' => $emissao->held_at?->toIso8601String(),
                'failedReason' => $emissao->failed_reason,
            ],
            createdAt: $emissao->created_at?->toIso8601String(),
        );
    }

    public static function dictEntry(DictEntry $entrada): ResourceRowView
    {
        return new ResourceRowView(
            id: $entrada->id,
            status: $entrada->status,
            attributes: [
                'pixKey' => $entrada->pix_key,
                'type' => $entrada->type->value,
                'name' => $entrada->name,
                'taxId' => $entrada->tax_id,
                'ownerType' => $entrada->owner_type->value,
                'bankName' => $entrada->bank_name,
            ],
            createdAt: $entrada->created_at?->toIso8601String(),
        );
    }

    public static function ledgerAccount(LedgerAccount $conta): ResourceRowView
    {
        return new ResourceRowView(
            id: $conta->asset,
            status: null,
            attributes: [
                'asset' => $conta->asset,
                'free' => $conta->free,
                'locked' => $conta->locked,
            ],
            createdAt: $conta->created_at?->toIso8601String(),
        );
    }

    public static function fiatOrder(FiatOrder $ordem): ResourceRowView
    {
        return new ResourceRowView(
            id: $ordem->order_no,
            // `effectiveStatus()` é leitura pura: a máscara do override que a
            // wire responderia, sem gravar nada.
            status: $ordem->effectiveStatus()->value,
            attributes: [
                'persistedStatus' => $ordem->status->value,
                'forcedStatus' => $ordem->forced_status?->value,
                'forcedWireStatus' => $ordem->forced_wire_status,
                'currency' => $ordem->currency,
                'amount' => $ordem->amount,
                'frozen' => $ordem->frozen,
                'creditedAt' => $ordem->credited_at?->toIso8601String(),
                'brcodeDelayReads' => $ordem->brcode_delay_reads,
                'brcodeReadsCount' => $ordem->brcode_reads_count,
                'brcodeVisible' => $ordem->brcodeVisible(),
            ],
            advance: AdvanceClock::project(
                $ordem->created_at,
                AdvanceClock::seconds('fake-binance-fiat.advance_seconds'),
                match (true) {
                    $ordem->forced_wire_status !== null => 'forcedWireStatus',
                    $ordem->forced_status !== null => 'forcedStatus',
                    $ordem->frozen => 'frozen',
                    default => null,
                },
            ),
            createdAt: $ordem->created_at?->toIso8601String(),
        );
    }

    public static function spotOrder(SpotOrder $ordem): ResourceRowView
    {
        // A ordem spot não avança por idade: o desfecho é resolvido no próprio
        // POST, então não há relógio a projetar.
        return new ResourceRowView(
            id: (string) $ordem->order_id,
            status: $ordem->status->value,
            attributes: [
                'clientOrderId' => $ordem->client_order_id,
                'symbol' => $ordem->symbol,
                'side' => $ordem->side->value,
                'executedQty' => $ordem->executed_qty,
                'cummulativeQuoteQty' => $ordem->cummulative_quote_qty,
                'rawStatusOverride' => $ordem->raw_status_override,
            ],
            createdAt: $ordem->created_at?->toIso8601String(),
        );
    }

    public static function withdrawal(Withdrawal $saque): ResourceRowView
    {
        return new ResourceRowView(
            id: $saque->id,
            // O status do saque é enum de INT na wire da Binance.
            status: (string) $saque->status->value,
            attributes: [
                'coin' => $saque->coin,
                'network' => $saque->network,
                'amount' => $saque->amount,
                'transactionFee' => $saque->transaction_fee,
                'address' => $saque->address,
                'txId' => $saque->tx_id,
                'info' => $saque->info,
                'frozen' => $saque->frozen,
                'rawStatusOverride' => $saque->raw_status_override,
            ],
            advance: AdvanceClock::project(
                $saque->applied_at,
                AdvanceClock::seconds('fake-binance-withdraw.advance_seconds'),
                match (true) {
                    $saque->raw_status_override !== null => 'rawStatusOverride',
                    $saque->frozen => 'frozen',
                    default => null,
                },
            ),
            createdAt: $saque->created_at?->toIso8601String(),
        );
    }

    public static function cryptoDeposit(CryptoDeposit $deposito): ResourceRowView
    {
        return new ResourceRowView(
            id: $deposito->id,
            status: (string) $deposito->status->value,
            attributes: [
                'coin' => $deposito->coin,
                'network' => $deposito->network,
                'amount' => $deposito->amount,
                'txId' => $deposito->tx_id,
                'address' => $deposito->address,
                'creditedAt' => $deposito->credited_at?->toIso8601String(),
            ],
            advance: AdvanceClock::project(
                $deposito->announced_at,
                AdvanceClock::seconds('fake-binance-deposit.advance_seconds'),
            ),
            createdAt: $deposito->created_at?->toIso8601String(),
        );
    }
}
