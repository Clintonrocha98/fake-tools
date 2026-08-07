<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Support;

use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;

/**
 * Todo log do fake-starkbank sai pelo canal dedicado `starkbank`
 * (storage/logs/starkbank-*.log): a malha PIX inteira é legível num arquivo
 * só, sem o ruído do resto do monolito. Logar direto no facade `Log` aqui
 * dentro é regressão — a linha some no laravel.log.
 */
final class StarkbankLog
{
    private const string CHANNEL = 'starkbank';

    /**
     * @param  array<string, mixed>  $context
     */
    public static function debug(string $message, array $context = []): void
    {
        self::channel()->debug($message, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function info(string $message, array $context = []): void
    {
        self::channel()->info($message, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function warning(string $message, array $context = []): void
    {
        self::channel()->warning($message, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function error(string $message, array $context = []): void
    {
        self::channel()->error($message, $context);
    }

    private static function channel(): LoggerInterface
    {
        return Log::channel(self::CHANNEL);
    }
}
