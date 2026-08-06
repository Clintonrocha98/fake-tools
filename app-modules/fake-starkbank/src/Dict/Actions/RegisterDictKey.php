<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Dict\Actions;

use He4rt\FakeStarkbank\Dict\DTOs\RegisterDictKeyData;
use He4rt\FakeStarkbank\Dict\Models\DictEntry;
use He4rt\FakeStarkbank\Dict\Support\OpaqueAccountBlob;
use Illuminate\Support\Facades\Log;

/**
 * Registra (ou reescreve) uma chave no DICT deste fake — o insumo sem o qual um
 * cash-out não tem beneficiário e o funding cross-fake não tem `taxId` para o
 * consumidor conferir.
 *
 * `updateOrCreate` sobre a chave PIX pelo mesmo motivo do seeder: um registro
 * DICT é estado DECLARATIVO. Registrar de novo a mesma chave com outro titular
 * é corrigir o registro, nunca criar um segundo dono para ela.
 *
 * Os blobs de agência e conta NÃO são informados por quem registra: são opacos
 * por contrato e derivados da chave, exatamente como o provedor os emite.
 */
final readonly class RegisterDictKey
{
    public function handle(RegisterDictKeyData $data): DictEntry
    {
        $entry = DictEntry::query()->updateOrCreate(
            ['pix_key' => $data->pixKey],
            [
                'type' => $data->type,
                'name' => $data->name,
                'tax_id' => $data->taxId,
                'owner_type' => $data->ownerType,
                'bank_name' => $data->bankName,
                'ispb' => $data->ispb,
                'branch_code_blob' => OpaqueAccountBlob::forBranch($data->pixKey),
                'account_number_blob' => OpaqueAccountBlob::forAccount($data->pixKey),
                'account_type' => $data->accountType,
                'status' => $data->status,
            ],
        );

        Log::info('fake-starkbank.dict: chave registrada sob comando — o registro é declarativo, então registrar a mesma chave de novo corrige o titular em vez de criar um segundo dono', [
            'pix_key' => $entry->pix_key,
            'tax_id' => $entry->tax_id,
            'owner_type' => $entry->owner_type->value,
        ]);

        return $entry;
    }
}
