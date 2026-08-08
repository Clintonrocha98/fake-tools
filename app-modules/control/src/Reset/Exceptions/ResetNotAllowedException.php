<?php

declare(strict_types=1);

namespace He4rt\Control\Reset\Exceptions;

use He4rt\Control\Http\ControlErrorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * O reset trunca tabelas — merece cinto além do kill-switch. 403 e não 404: a
 * rota existe, o ambiente é que não autoriza, e essa distinção é o que diz ao
 * dev que ele configurou algo errado em vez de errar a URL.
 */
final class ResetNotAllowedException extends RuntimeException
{
    /** @var list<string> */
    public array $permitidos = [];

    /**
     * @param  list<string>  $permitidos
     */
    public static function forEnvironment(string $ambiente, array $permitidos): self
    {
        $excecao = new self(sprintf(
            'O reset de baseline não roda no ambiente "%s". Permitidos: %s.',
            $ambiente,
            implode(', ', $permitidos),
        ));

        $excecao->permitidos = $permitidos;

        return $excecao;
    }

    public function render(Request $request): JsonResponse
    {
        return ControlErrorResponse::make($this->getMessage(), 403, $this->permitidos);
    }
}
