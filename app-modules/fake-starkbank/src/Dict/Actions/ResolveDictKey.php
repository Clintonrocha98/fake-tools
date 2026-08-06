<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Dict\Actions;

use He4rt\FakeStarkbank\Dict\Exceptions\DictKeyNotFoundException;
use He4rt\FakeStarkbank\Dict\Models\DictEntry;
use Illuminate\Support\Facades\Log;

/**
 * `GET /v2/dict-key/{key}` — a resolução que antecede todo cash-out. Leitura
 * pura: o registro do fake não muda por ser consultado.
 */
final readonly class ResolveDictKey
{
    public function handle(string $pixKey): DictEntry
    {
        $entry = DictEntry::query()->where('pix_key', $pixKey)->first();

        if (!$entry instanceof DictEntry) {
            Log::warning('fake-starkbank.dict: chave não registrada — 404 em vez de resolução vazia, porque é o throw do consumidor que segura o Payout em Withheld antes de transferir para um beneficiário em branco', [
                'pix_key' => $pixKey,
            ]);

            throw DictKeyNotFoundException::forKey($pixKey);
        }

        Log::info('fake-starkbank.dict: chave resolvida — os blobs de agência e conta saem opacos de propósito, para que o consumidor os ecoe verbatim no POST /v2/transfer', [
            'pix_key' => $entry->pix_key,
            'owner_type' => $entry->owner_type->value,
            'ispb' => $entry->ispb,
        ]);

        return $entry;
    }
}
