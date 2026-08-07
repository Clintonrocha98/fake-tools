<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Scenarios\Http\Middleware;

use Closure;
use He4rt\FakeStarkbank\Http\Errors\ErrorResponseFactory;
use He4rt\FakeStarkbank\Http\Errors\StarkbankErrorCode;
use He4rt\FakeStarkbank\Scenarios\Actions\GetScenarioSwitchboard;
use He4rt\FakeStarkbank\Support\StarkbankLog;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Roda antes de `fake-starkbank.signed` em toda rota do módulo — atrás apenas
 * do `fake-starkbank.request-log`, que registra até o request que este
 * middleware derruba —, para que os switches globais recusem o request sem
 * sequer validar assinatura: é exatamente essa indisponibilidade "antes de
 * qualquer lógica" que o tratamento de erro do consumidor precisa exercitar.
 * Quando os dois switches estão ligados ao mesmo tempo, outage vence rate
 * limit — a mesma ordem do enum {@see \He4rt\FakeStarkbank\Scenarios\Enums\PixScenarioSwitch}.
 */
final readonly class ApplyPixScenarioSwitches
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
            StarkbankLog::warning('fake-starkbank.scenarios: request derrubado pelo modo outage — a recusa vem antes da assinatura de propósito, para o consumidor ver a indisponibilidade e não um 401', [
                'path' => $request->path(),
                'method' => $request->method(),
            ]);

            return $this->errors->make(StarkbankErrorCode::InternalServerError);
        }

        if ($switches->rate_limit_mode) {
            StarkbankLog::warning('fake-starkbank.scenarios: request derrubado pelo modo rate limit — o Retry-After sai do próprio switchboard, para o consumidor exercitar o backoff que ele implementa', [
                'path' => $request->path(),
                'retry_after' => $switches->rate_limit_retry_after_seconds,
            ]);

            return $this->errors->make(StarkbankErrorCode::TooManyRequests)
                ->withHeaders(['Retry-After' => (string) $switches->rate_limit_retry_after_seconds]);
        }

        return $next($request);
    }
}
