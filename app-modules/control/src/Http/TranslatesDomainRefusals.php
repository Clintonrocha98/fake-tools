<?php

declare(strict_types=1);

namespace He4rt\Control\Http;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Uma recusa das Actions dos fakes vira 422 no plano de controle, nunca 500.
 *
 * O 500 é a resposta certa para um defeito do fake; uma transição que a Action
 * recusa é o fake funcionando — e o dev precisa ler o motivo, não um stack
 * trace.
 *
 * Isto NÃO pode ser um middleware: `Illuminate\Routing\Pipeline` converte a
 * exceção em resposta dentro do próprio estágio onde ela é lançada, então um
 * `try`/`catch` num middleware de rota nunca a vê. O gancho que funciona é o
 * `renderable()` do handler.
 *
 * O escopo é o prefixo `/control`: as rotas dos fakes têm envelope de erro
 * próprio, que é parte do que eles dublam, e traduzi-las aqui quebraria a
 * fidelidade que o consumidor testa.
 */
final readonly class TranslatesDomainRefusals
{
    /**
     * Os namespaces cujas exceções são recusa de negócio, não defeito.
     *
     * @var list<string>
     */
    private const array DOMINIOS = ['He4rt\FakeBinance\\', 'He4rt\FakeStarkbank\\'];

    public static function register(ExceptionHandler $handler): void
    {
        if (!$handler instanceof Handler) {
            return;
        }

        $handler->renderable(static function (Throwable $excecao, Request $request): ?JsonResponse {
            if (!$request->is('control/*') || !self::ehRecusaDeDominio($excecao)) {
                return null;
            }

            return ControlErrorResponse::make($excecao->getMessage(), 422);
        });
    }

    private static function ehRecusaDeDominio(Throwable $excecao): bool
    {
        return array_any(self::DOMINIOS, fn (string $dominio) => str_starts_with($excecao::class, $dominio));
    }
}
