<?php

declare(strict_types=1);

namespace He4rt\Control\Reset\DTOs;

/**
 * O que o reset apagou, por tabela. É o retorno que o operador lê no terminal e
 * a sidebar mostra depois do gesto — sem ele, "resetado" é uma afirmação que
 * ninguém consegue conferir.
 */
final readonly class BaselineResetReport
{
    /**
     * @param  array<string, int>  $deleted  tabela => linhas removidas
     */
    public function __construct(
        public array $deleted,
        public string $resetAt,
    ) {}

    public function total(): int
    {
        return array_sum($this->deleted);
    }

    /**
     * @return array{deleted: array<string, int>, total: int, resetAt: string}
     */
    public function toArray(): array
    {
        return [
            'deleted' => $this->deleted,
            'total' => $this->total(),
            'resetAt' => $this->resetAt,
        ];
    }
}
