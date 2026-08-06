<?php

declare(strict_types=1);

namespace He4rt\PanelAdmin\Tests\Feature\Filament;

use Filament\Facades\Filament;
use He4rt\FakeStarkbank\Dict\Enums\DictKeyType;
use He4rt\FakeStarkbank\Dict\Enums\DictOwnerType;
use He4rt\FakeStarkbank\Dict\Models\DictEntry;
use He4rt\Identity\Permissions\Roles;
use He4rt\Identity\Users\User;
use He4rt\PanelAdmin\Filament\Resources\StarkbankDictEntries\Pages\ListStarkbankDictEntries;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    actingAs(User::factory()->create());
    auth()->user()->assignRole(Roles::SuperAdmin->value);
});

it('lista as chaves registradas no DICT', function (): void {
    $entries = DictEntry::factory()->count(2)->create();

    livewire(ListStarkbankDictEntries::class)
        ->assertOk()
        ->loadTable()
        ->assertCanSeeTableRecords($entries);
});

it('registra uma chave de dev pela ação de cabeçalho', function (): void {
    livewire(ListStarkbankDictEntries::class)
        ->callAction('registerDictKey', [
            'pix_key' => 'tesouraria@brd.digital',
            'type' => DictKeyType::Email->value,
            'name' => 'Tesouraria BRD',
            'tax_id' => '20.018.183/0001-80',
            'owner_type' => DictOwnerType::LegalEntity->value,
        ])
        ->assertNotified();

    $entry = DictEntry::query()->where('pix_key', 'tesouraria@brd.digital')->sole();

    expect($entry->name)->toBe('Tesouraria BRD')
        ->and($entry->owner_type)->toBe(DictOwnerType::LegalEntity)
        // Blobs opacos nunca são digitados: o provedor os deriva da chave.
        ->and($entry->branch_code_blob)->not->toBeEmpty()
        ->and($entry->account_number_blob)->not->toBeEmpty();
});

it('corrige o titular ao registrar a mesma chave de novo, sem criar um segundo dono', function (): void {
    DictEntry::factory()->create(['pix_key' => 'tesouraria@brd.digital', 'name' => 'Nome antigo']);

    livewire(ListStarkbankDictEntries::class)
        ->callAction('registerDictKey', [
            'pix_key' => 'tesouraria@brd.digital',
            'type' => DictKeyType::Email->value,
            'name' => 'Tesouraria BRD',
            'tax_id' => '20.018.183/0001-80',
            'owner_type' => DictOwnerType::LegalEntity->value,
        ]);

    expect(DictEntry::query()->where('pix_key', 'tesouraria@brd.digital')->count())->toBe(1)
        ->and(DictEntry::query()->where('pix_key', 'tesouraria@brd.digital')->sole()->name)->toBe('Tesouraria BRD');
});
