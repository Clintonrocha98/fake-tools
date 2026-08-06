<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Transfer\Actions;

use He4rt\FakeStarkbank\Support\NumericId;
use He4rt\FakeStarkbank\Transfer\DTOs\SendTransferData;
use He4rt\FakeStarkbank\Transfer\Enums\TransferStatus;
use He4rt\FakeStarkbank\Transfer\Models\Transfer;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;

/**
 * `POST /v2/transfer` — despacha o PIX de saída em
 * {@see TransferStatus::Created}.
 *
 * Idempotente por `externalId`, como o provedor: um segundo POST com o mesmo
 * valor devolve a MESMA transfer no estado atual, nunca uma segunda saída de
 * dinheiro. A releitura de idempotência também faz o tempo passar — é uma
 * leitura como qualquer outra —, então um retry tardio já vê a transfer
 * liquidada.
 *
 * Sem `externalId` não há o que deduplicar e cada POST cria uma transfer nova:
 * o campo é opcional no contrato, e inventar uma chave a partir do conteúdo
 * fundiria dois pagamentos legítimos de mesmo valor para o mesmo beneficiário.
 */
final readonly class SendTransfer
{
    public function __construct(private AdvanceTransferStatus $advance) {}

    public function handle(SendTransferData $data): Transfer
    {
        $existing = $this->findByExternalId($data->externalId);

        if ($existing instanceof Transfer) {
            Log::info('fake-starkbank.transfer: POST repetido com o mesmo externalId — devolvendo a transfer existente, porque duplicar aqui seria pagar o mesmo Payout duas vezes', [
                'transfer_id' => $existing->id,
                'external_id' => $data->externalId,
                'status' => $existing->status->value,
            ]);

            return $this->advance->handle($existing);
        }

        try {
            $transfer = Transfer::query()->create([
                'id' => NumericId::generate(),
                'amount' => $data->amount,
                'name' => $data->name,
                'tax_id' => $data->taxId,
                'bank_code' => $data->bankCode,
                'branch_code' => $data->branchCode,
                'account_number' => $data->accountNumber,
                'account_type' => $data->accountType,
                'external_id' => $data->externalId,
                'status' => TransferStatus::Created,
                'tags' => $data->tags,
            ]);
        } catch (UniqueConstraintViolationException $uniqueConstraintViolationException) {
            // Dois POST simultâneos com o mesmo externalId: quem perde a corrida
            // relê a linha do vencedor em vez de estourar. O índice único é a
            // trava de verdade; a checagem acima é só o caminho comum. Uma
            // colisão sem linha correspondente é outra restrição violada —
            // deixa subir, esconder isso mascararia um defeito de schema.
            $raced = $this->findByExternalId($data->externalId);

            throw_unless($raced instanceof Transfer, $uniqueConstraintViolationException);

            Log::warning('fake-starkbank.transfer: corrida de idempotência resolvida pelo índice único — dois POST com o mesmo externalId chegaram juntos e só um virou transfer', [
                'transfer_id' => $raced->id,
                'external_id' => $data->externalId,
            ]);

            return $this->advance->handle($raced);
        }

        Log::info('fake-starkbank.transfer: cash-out despachado — os blobs de agência e conta entram como chegaram, porque são opacos por contrato e quem os emitiu foi o DICT', [
            'transfer_id' => $transfer->id,
            'amount' => $transfer->amount,
            'bank_code' => $transfer->bank_code,
            'external_id' => $transfer->external_id,
            'correlation_id' => $transfer->tags->correlationId(),
        ]);

        return $transfer;
    }

    private function findByExternalId(?string $externalId): ?Transfer
    {
        if ($externalId === null) {
            return null;
        }

        return Transfer::query()->where('external_id', $externalId)->first();
    }
}
