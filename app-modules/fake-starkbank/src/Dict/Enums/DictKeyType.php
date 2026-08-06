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
 * As quatro formas de chave que o DICT registra, exatamente como o valor sai em
 * `key.type` na wire. O consumidor não ramifica por este campo — ele apenas
 * ecoa a resolução —, mas o painel de cenários precisa distinguir uma chave de
 * e-mail de uma aleatória ao montar um beneficiário.
 */
enum DictKeyType: string implements HasColor, HasDescription, HasIcon, HasLabel
{
    case Email = 'email';
    case Phone = 'phone';
    case Document = 'document';
    case Random = 'random';

    public function getLabel(): string
    {
        return match ($this) {
            self::Email => 'E-mail',
            self::Phone => 'Telefone',
            self::Document => 'CPF/CNPJ',
            self::Random => 'Chave aleatória',
        };
    }

    /**
     * Enum não-ordenado: as quatro formas são alternativas equivalentes, não
     * uma escala — cada uma recebe cor própria, sem ramp.
     *
     * @return array<int, string>
     */
    public function getColor(): array
    {
        return match ($this) {
            self::Email => Color::Sky,
            self::Phone => Color::Teal,
            self::Document => Color::Amber,
            self::Random => Color::Violet,
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Email => 'Chave registrada como endereço de e-mail',
            self::Phone => 'Chave registrada como telefone em formato E.164',
            self::Document => 'Chave registrada como o próprio CPF ou CNPJ do titular',
            self::Random => 'Chave aleatória (EVP) gerada pelo banco do titular',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Email => Heroicon::OutlinedEnvelope,
            self::Phone => Heroicon::OutlinedDevicePhoneMobile,
            self::Document => Heroicon::OutlinedIdentification,
            self::Random => Heroicon::OutlinedSparkles,
        };
    }
}
