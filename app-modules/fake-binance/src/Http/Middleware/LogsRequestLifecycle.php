<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Http\Middleware;

use Closure;
use He4rt\FakeBinance\Support\BinanceLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ciclo de vida da request no canal `binance`: uma linha na entrada (antes de
 * cenário e assinatura, então até um request que será recusado aparece) e uma
 * na saída (status e corpo como o consumidor os recebe). O `request_id` entra
 * no Context — todo log da request e dos jobs que ela despachar sai amarrado a
 * ele — e volta no header `X-Fake-Request-Id` para o dev do consumidor achar a
 * linha certa no log.
 *
 * A `signature` da query é redigida e a `X-MBX-APIKEY` nunca é logada: o log
 * existe para depurar o consumidor, não para vazar o par de dev.
 */
final readonly class LogsRequestLifecycle
{
    private const int MAX_BODY_CHARS = 8_192;

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = Str::uuid()->toString();

        Context::add('request_id', $requestId);

        $inicio = hrtime(as_number: true);

        BinanceLog::info('fake-binance.http: request entrou — payload registrado antes de cenário e assinatura', [
            'method' => $request->method(),
            'path' => '/'.$request->path(),
            'query' => $this->redactedQuery($request),
            'body' => $this->truncate($request->getContent()),
            'has_api_key' => $request->hasHeader('X-MBX-APIKEY'),
        ]);

        $response = $next($request);

        BinanceLog::info('fake-binance.http: response saiu — status e corpo como o consumidor os recebe', [
            'method' => $request->method(),
            'path' => '/'.$request->path(),
            'status' => $response->getStatusCode(),
            'duration_ms' => round((hrtime(as_number: true) - $inicio) / 1e6, 1),
            'body' => $this->truncate($response->getContent()),
        ]);

        $response->headers->set('X-Fake-Request-Id', $requestId);

        return $response;
    }

    /**
     * A assinatura HMAC viaja na query junto do payload de negócio — o payload
     * interessa ao debug, a assinatura não, e redigir preserva a presença.
     *
     * @return array<string, mixed>
     */
    private function redactedQuery(Request $request): array
    {
        /** @var array<string, mixed> $query */
        $query = $request->query();

        if (array_key_exists('signature', $query)) {
            $query['signature'] = '[redigida]';
        }

        return $query;
    }

    private function truncate(string|false $content): ?string
    {
        if ($content === false) {
            return '[corpo não bufferizado]';
        }

        if ($content === '') {
            return null;
        }

        if (mb_strlen($content) <= self::MAX_BODY_CHARS) {
            return $content;
        }

        return mb_substr($content, 0, self::MAX_BODY_CHARS).'… [truncado]';
    }
}
