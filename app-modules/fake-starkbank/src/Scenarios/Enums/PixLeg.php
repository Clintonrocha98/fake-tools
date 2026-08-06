<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Scenarios\Enums;

use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;
use He4rt\FakeStarkbank\Scenarios\Contracts\PixLegOutcomeContract;

/**
 * As pernas da malha PIX que aceitam um cenário armado — uma por família de
 * pedido/evento que o consumidor faz. No máximo um cenário armado por perna,
 * garantido pelo índice único de `leg` em `fake_starkbank_armed_scenarios`;
 * pernas diferentes ficam armadas ao mesmo tempo sem se atrapalhar.
 *
 * Três delas são ASSÍNCRONAS (invoice, transfer, brcode-payment): o armado é
 * consumido no `POST` de criação e o registro nasce destinado, de forma que as
 * leituras seguintes só executam o destino já gravado. A quarta é POR EVENTO
 * (webhook): o armado é consumido no instante da emissão, não na criação do
 * recurso de origem.
 */
enum PixLeg: string implements HasColor, HasDescription, HasIcon, HasLabel
{
    case StarkbankInvoice = 'starkbank_invoice';
    case StarkbankTransfer = 'starkbank_transfer';
    case StarkbankBrcodePayment = 'starkbank_brcode_payment';
    case StarkbankWebhook = 'starkbank_webhook';

    /**
     * @return list<PixLegOutcomeContract>
     */
    public function outcomes(): array
    {
        return match ($this) {
            self::StarkbankInvoice => InvoiceOutcome::cases(),
            self::StarkbankTransfer => TransferOutcome::cases(),
            self::StarkbankBrcodePayment => BrcodePaymentOutcome::cases(),
            self::StarkbankWebhook => WebhookOutcome::cases(),
        };
    }

    /**
     * `null` quando o valor não é um desfecho DESTA perna. Um `outcome`
     * obsoleto na coluna (caso removido do enum, edição manual) faz quem
     * planeja cair no plano neutro, em vez de estourar dentro do POST do
     * consumidor e o fake responder 500 num cenário que ele deveria ignorar.
     */
    public function outcomeFrom(string $value): ?PixLegOutcomeContract
    {
        return match ($this) {
            self::StarkbankInvoice => InvoiceOutcome::tryFrom($value),
            self::StarkbankTransfer => TransferOutcome::tryFrom($value),
            self::StarkbankBrcodePayment => BrcodePaymentOutcome::tryFrom($value),
            self::StarkbankWebhook => WebhookOutcome::tryFrom($value),
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::StarkbankInvoice => 'Invoice',
            self::StarkbankTransfer => 'Transfer',
            self::StarkbankBrcodePayment => 'BrcodePayment',
            self::StarkbankWebhook => 'Webhook',
        };
    }

    /**
     * @return array<int, string>
     */
    public function getColor(): array
    {
        return match ($this) {
            self::StarkbankInvoice => Color::Emerald,
            self::StarkbankTransfer => Color::Indigo,
            self::StarkbankBrcodePayment => Color::Amber,
            self::StarkbankWebhook => Color::Fuchsia,
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::StarkbankInvoice => 'POST /v2/invoice — o desfecho é gravado na criação e a próxima releitura já o entrega',
            self::StarkbankTransfer => 'POST /v2/transfer — o desfecho é gravado na criação e a próxima releitura já o entrega',
            self::StarkbankBrcodePayment => 'POST /v2/brcode-payment — o desfecho é gravado na criação e a próxima releitura já o entrega',
            self::StarkbankWebhook => 'Vale para a PRÓXIMA emissão de evento, seja de qual perna for',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::StarkbankInvoice => Heroicon::OutlinedQrCode,
            self::StarkbankTransfer => Heroicon::OutlinedArrowUpOnSquare,
            self::StarkbankBrcodePayment => Heroicon::OutlinedBanknotes,
            self::StarkbankWebhook => Heroicon::OutlinedBellAlert,
        };
    }
}
