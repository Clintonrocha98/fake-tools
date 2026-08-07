<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Http\Middleware;

use Closure;
use He4rt\FakeStarkbank\Support\StarkbankLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ciclo de vida da request no canal `starkbank`: uma linha na entrada (antes
 * de cenário e assinatura, então até um request que será recusado aparece) e
 * uma na saída (status e corpo como o consumidor os recebe). O `request_id`
 * entra no Context — todo log da request e dos jobs que ela despachar, webhook
 * incluso, sai amarrado a ele — e volta no header `X-Fake-Request-Id` para o
 * dev do consumidor achar a linha certa no log.
 *
 * `Access-Id` e `Access-Time` são logados porque identificam quem chamou e
 * quando; a `Access-Signature` nunca é — o log existe para depurar o
 * consumidor, não para colecionar assinaturas.
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

        StarkbankLog::info('fake-starkbank.http: request entrou — payload registrado antes de cenário e assinatura', [
            'method' => $request->method(),
            'path' => '/'.$request->path(),
            'query' => $request->query(),
            'body' => $this->truncate($request->getContent()),
            'access_id' => $request->header('Access-Id'),
            'access_time' => $request->header('Access-Time'),
        ]);

        $response = $next($request);

        StarkbankLog::info('fake-starkbank.http: response saiu — status e corpo como o consumidor os recebe', [
            'method' => $request->method(),
            'path' => '/'.$request->path(),
            'status' => $response->getStatusCode(),
            'duration_ms' => round((hrtime(as_number: true) - $inicio) / 1e6, 1),
            'body' => $this->truncate($response->getContent()),
        ]);

        $response->headers->set('X-Fake-Request-Id', $requestId);

        return $response;
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
