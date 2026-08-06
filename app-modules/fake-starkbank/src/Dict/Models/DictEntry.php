<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Dict\Models;

use App\Models\BaseModel;
use He4rt\FakeStarkbank\Database\Factories\Dict\DictEntryFactory;
use He4rt\FakeStarkbank\Dict\Enums\DictKeyType;
use He4rt\FakeStarkbank\Dict\Enums\DictOwnerType;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Support\Carbon;

/**
 * Uma chave registrada no DICT deste fake — o insumo que
 * `GET /v2/dict-key/{key}` resolve antes de todo cash-out.
 *
 * O registro é o único lugar onde o beneficiário de uma transfer existe: o fake
 * aceita qualquer `branchCode`/`accountNumber` no POST, mas a resolução que os
 * originou sai daqui.
 *
 * @property string $id
 * @property string $pix_key
 * @property DictKeyType $type
 * @property string $name
 * @property string $tax_id
 * @property DictOwnerType $owner_type
 * @property string $bank_name
 * @property string $ispb
 * @property string $branch_code_blob
 * @property string $account_number_blob
 * @property string $account_type
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @extends BaseModel<DictEntryFactory>
 */
#[UseFactory(factoryClass: DictEntryFactory::class)]
#[Table(name: 'fake_starkbank_dict_entries')]
final class DictEntry extends BaseModel
{
    protected function casts(): array
    {
        return [
            'type' => DictKeyType::class,
            'owner_type' => DictOwnerType::class,
        ];
    }
}
