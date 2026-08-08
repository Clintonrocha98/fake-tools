<?php

declare(strict_types=1);

namespace He4rt\Control\State\DTOs;

/**
 * Os registros recentes de um tipo. `total` é a contagem inteira da tabela e
 * `rows` só o topo: o retrato é para caber numa tela, não para paginar — o que
 * precisar de paginação pertence a uma rota própria.
 */
final readonly class ResourceGroupView
{
    /**
     * @param  list<ResourceRowView>  $rows
     */
    public function __construct(
        public string $type,
        public int $total,
        public array $rows,
    ) {}

    /**
     * @return array{total: int, rows: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'total' => $this->total,
            'rows' => array_map(static fn (ResourceRowView $row): array => $row->toArray(), $this->rows),
        ];
    }
}
