<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Support;

use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;

/**
 * Todo log do fake-binance sai pelo canal dedicado `binance`
 * (storage/logs/binance-*.log): a malha inteira de uma integração é legível
 * num arquivo só, sem o ruído do resto do monolito. Logar direto no facade
 * `Log` aqui dentro é regressão — a linha some no laravel.log.
 */
final class BinanceLog
{
    private const string CHANNEL = 'binance';

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
