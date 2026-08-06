<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Brcode\Actions;

use He4rt\FakeStarkbank\Brcode\DTOs\BrcodePreviewView;
use He4rt\FakeStarkbank\Brcode\DTOs\DecodedBrcode;
use He4rt\FakeStarkbank\Dict\Actions\ResolveDictKey;
use He4rt\FakeStarkbank\Dict\Exceptions\DictKeyNotFoundException;
use He4rt\FakeStarkbank\Dict\Models\DictEntry;
use Illuminate\Support\Facades\Log;

/**
 * `GET /v2/brcode-preview?brcodes={brcode}` — o insumo que o consumidor confere
 * ANTES de pagar o funding da venue.
 *
 * Duas fontes, nunca config: o CÓDIGO diz o valor (campo 54) e a chave PIX
 * (campo 26), e o REGISTRO DICT diz quem é o recebedor daquela chave. É a
 * junção das duas que dá sentido aos guards do `SendConversionFunding`: o
 * `taxId` conferido contra `treasury.conversion.funding_expected_tax_id` sai do
 * DICT, e o `amount` conferido contra a Conversion sai dos bytes do EMV.
 *
 * Chave bem-formada mas fora do registro NÃO é recusa: o preview responde 200
 * com `taxId`/`bankCode` vazios, e quem recusa é o consumidor
 * (`FundingNotSendable::destinationUnverifiable`). Recusar aqui trocaria o
 * fail-closed dele por um erro de transporte.
 */
final readonly class PreviewBrcode
{
    /**
     * O único `status` que um preview de BR Code pagável carrega. O provedor
     * usa o campo para marcar códigos já pagos ou vencidos — conceito que não
     * existe num BR Code estático, e o fake serve a constante.
     */
    private const string ACTIVE = 'active';

    public function __construct(
        private DecodeBrcode $decode,
        private ResolveDictKey $resolveDictKey,
    ) {}

    public function handle(string $brcode): BrcodePreviewView
    {
        $decoded = $this->decode->handle($brcode);
        $entry = $this->tryResolve($decoded);

        $amount = $decoded->amountCentavos ?? 0;
        $registrado = $entry instanceof DictEntry;

        $preview = new BrcodePreviewView(
            status: self::ACTIVE,
            // O nome do titular vem do DICT quando a chave está registrada; o
            // campo 59 do EMV é o fallback, porque é texto livre de quem
            // emitiu o código e não uma identidade que alguém verificou.
            name: $registrado && $entry->name !== '' ? $entry->name : $decoded->receiverName,
            taxId: $registrado ? $entry->tax_id : '',
            bankCode: $registrado ? $entry->ispb : '',
            accountType: $registrado ? $entry->account_type : '',
            allowChange: $decoded->allowsAmountChange(),
            amount: $amount,
            nominalAmount: $amount,
            reconciliationId: $this->constant('fake-starkbank-brcode.preview.reconciliation_id', 'recon-9f2c'),
            description: $this->constant('fake-starkbank-brcode.preview.description', 'Invoice 2026-07'),
        );

        Log::info('fake-starkbank.brcode: preview servido a partir dos bytes do EMV — o valor sai do campo 54 e o recebedor do registro DICT, nunca de config, senão os dois guards do consumidor passariam sempre', [
            'pix_key' => $decoded->pixKey,
            'amount' => $preview->amount,
            'allow_change' => $preview->allowChange,
            'tax_id_resolvido' => $preview->taxId !== '',
        ]);

        return $preview;
    }

    private function tryResolve(DecodedBrcode $decoded): ?DictEntry
    {
        try {
            return $this->resolveDictKey->handle($decoded->pixKey);
        } catch (DictKeyNotFoundException) {
            Log::warning('fake-starkbank.brcode: chave do BR Code fora do registro DICT — preview servido com taxId vazio de propósito, que é o sinal de "destino não verificável" que faz o consumidor recusar sozinho antes de pagar', [
                'pix_key' => $decoded->pixKey,
            ]);

            return null;
        }
    }

    private function constant(string $key, string $default): string
    {
        $value = config($key, $default);

        return is_scalar($value) && (string) $value !== '' ? (string) $value : $default;
    }
}
