<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Scenarios\Http\Middleware;

use Closure;
use He4rt\FakeBinance\Http\Errors\BinanceErrorCode;
use He4rt\FakeBinance\Http\Errors\ErrorFamily;
use He4rt\FakeBinance\Http\Errors\ErrorResponseFactory;
use He4rt\FakeBinance\Scenarios\Actions\GetScenarioSwitchboard;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Primeiro middleware de toda rota do módulo — antes até de `fake-binance.signed`, para
 * que os três switches globais recusem o request sem sequer validar assinatura
 * (é exatamente essa indisponibilidade "antes de qualquer lógica" que o
 * `BinanceErrorBoundary` do monolito consumidor precisa exercitar). Quando mais
 * de um switch está ligado ao mesmo tempo, a ordem de precedência é outage >
 * rate limit > relógio torto — a mesma do enum {@see \He4rt\FakeBinance\Scenarios\Enums\ScenarioSwitch}.
 */
final readonly class ApplyScenarioSwitches
{
    public function __construct(
        private GetScenarioSwitchboard $switchboard,
        private ErrorResponseFactory $errors,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $switches = $this->switchboard->handle();

        if ($switches->outage_mode) {
            $family = ErrorFamily::fromPath($request->path());

            return $this->errors->make($family, BinanceErrorCode::InternalError);
        }

        if ($switches->rate_limit_mode) {
            $family = ErrorFamily::fromPath($request->path());

            return $this->errors->make($family, BinanceErrorCode::TooManyRequests)
                ->withHeaders(['Retry-After' => (string) $switches->rate_limit_retry_after_seconds]);
        }

        if ($switches->clock_skew_mode) {
            $family = ErrorFamily::fromPath($request->path());

            return $this->errors->make($family, BinanceErrorCode::TimestampOutOfWindow);
        }

        return $next($request);
    }
}
