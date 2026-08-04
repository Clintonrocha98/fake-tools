<?php

declare(strict_types=1);

namespace He4rt\Venue\Scenarios\Http\Middleware;

use Closure;
use He4rt\Venue\Http\Errors\BinanceErrorCode;
use He4rt\Venue\Http\Errors\ErrorFamily;
use He4rt\Venue\Http\Errors\VenueErrorResponseFactory;
use He4rt\Venue\Scenarios\Actions\GetScenarioSwitchboard;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Primeiro middleware de toda rota do venue — antes até de `venue.signed`, para
 * que os três switches globais recusem o request sem sequer validar assinatura
 * (é exatamente essa indisponibilidade "antes de qualquer lógica" que o
 * `BinanceErrorBoundary` do monolito consumidor precisa exercitar). Quando mais
 * de um switch está ligado ao mesmo tempo, a ordem de precedência é outage >
 * rate limit > relógio torto — a mesma do enum {@see \He4rt\Venue\Scenarios\Enums\ScenarioSwitch}.
 */
final readonly class ApplyScenarioSwitches
{
    public function __construct(
        private GetScenarioSwitchboard $switchboard,
        private VenueErrorResponseFactory $errors,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $switches = ($this->switchboard)();

        if ($switches->outage_mode) {
            return response()->json([
                'code' => -1_001,
                'msg' => 'Internal error; unable to process your request. Please try again.',
            ], 503);
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
