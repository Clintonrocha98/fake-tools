<?php

declare(strict_types=1);

namespace He4rt\Control\Http\Exceptions;

use He4rt\Control\Http\ControlErrorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * O segmento `{fake}` da rota não resolve para nenhuma bridge — 404, porque o
 * recurso pedido não existe (e não porque o pedido estava malformado).
 */
final class UnknownFakeException extends RuntimeException
{
    /** @var list<string> */
    public array $conhecidos = [];

    /**
     * @param  list<string>  $conhecidos
     */
    public static function for(string $fake, array $conhecidos): self
    {
        $excecao = new self(sprintf(
            'Fake desconhecido "%s". Os disponíveis são: %s.',
            $fake,
            implode(', ', $conhecidos),
        ));

        $excecao->conhecidos = $conhecidos;

        return $excecao;
    }

    /**
     * O handler do Laravel chama este método sozinho — sem ele, cada controller
     * do plano de controle repetiria o mesmo `try`/`catch`.
     */
    public function render(Request $request): JsonResponse
    {
        return ControlErrorResponse::make($this->getMessage(), 404, $this->conhecidos);
    }
}
