<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Dict\Enums;

use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Quem é o titular da chave, no camelCase da wire (`key.ownerType`). É o campo
 * que separa o beneficiário pessoa física do cash-out (CPF) da pessoa jurídica
 * que recebe o funding cross-fake (CNPJ).
 */
enum DictOwnerType: string implements HasColor, HasDescription, HasIcon, HasLabel
{
    case NaturalPerson = 'naturalPerson';
    case LegalEntity = 'legalEntity';

    public function getLabel(): string
    {
        return match ($this) {
            self::NaturalPerson => 'Pessoa física',
            self::LegalEntity => 'Pessoa jurídica',
        };
    }

    /**
     * Enum não-ordenado: os dois titulares são naturezas distintas, não uma
     * escala — cada um recebe cor própria, sem ramp.
     *
     * @return array<int, string>
     */
    public function getColor(): array
    {
        return match ($this) {
            self::NaturalPerson => Color::Emerald,
            self::LegalEntity => Color::Indigo,
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::NaturalPerson => 'Titular pessoa física — o taxId é um CPF',
            self::LegalEntity => 'Titular pessoa jurídica — o taxId é um CNPJ',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::NaturalPerson => Heroicon::OutlinedUser,
            self::LegalEntity => Heroicon::OutlinedBuildingOffice2,
        };
    }
}
