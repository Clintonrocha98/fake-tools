<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Dict\DTOs;

use BackedEnum;
use He4rt\FakeStarkbank\Dict\Enums\DictKeyType;
use He4rt\FakeStarkbank\Dict\Enums\DictOwnerType;

/**
 * Os dados com que um operador registra uma chave PIX de dev. Os defaults de
 * banco vêm da config do módulo — quem registra uma chave está interessado no
 * titular, não em reescrever o banco fictício a cada vez.
 */
final readonly class RegisterDictKeyData
{
    public function __construct(
        public string $pixKey,
        public DictKeyType $type,
        public string $name,
        public string $taxId,
        public DictOwnerType $ownerType,
        public string $bankName,
        public string $ispb,
        public string $accountType,
        public string $status,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromForm(array $data): self
    {
        return new self(
            pixKey: self::text($data['pix_key'] ?? null, ''),
            type: DictKeyType::tryFrom(self::text($data['type'] ?? null, 'email')) ?? DictKeyType::Email,
            name: self::text($data['name'] ?? null, ''),
            taxId: self::text($data['tax_id'] ?? null, ''),
            ownerType: DictOwnerType::tryFrom(self::text($data['owner_type'] ?? null, 'naturalPerson')) ?? DictOwnerType::NaturalPerson,
            bankName: self::text($data['bank_name'] ?? null, self::config('fake-starkbank-dict.bank.name', 'Stark Bank S.A.')),
            ispb: self::text($data['ispb'] ?? null, self::config('fake-starkbank-dict.bank.ispb', '20018183')),
            accountType: self::text($data['account_type'] ?? null, self::config('fake-starkbank-dict.bank.account_type', 'checking')),
            status: self::config('fake-starkbank-dict.bank.status', 'registered'),
        );
    }

    /**
     * O `Select` do Filament configurado com `options(Enum::class)` hidrata o
     * estado como instância do enum, não como a string do banco — normalizar
     * aqui evita que a escolha do operador caia silenciosamente no default.
     */
    private static function text(mixed $value, string $default): string
    {
        $value = $value instanceof BackedEnum ? $value->value : $value;

        return is_scalar($value) && (string) $value !== '' ? (string) $value : $default;
    }

    private static function config(string $key, string $default): string
    {
        return self::text(config($key, $default), $default);
    }
}
