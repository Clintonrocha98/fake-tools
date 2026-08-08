<?php

declare(strict_types=1);

namespace He4rt\Control\State\DTOs;

/**
 * Uma linha do retrato. `attributes` é genuinamente polimórfico — cada tipo de
 * recurso mostra o que importa nele —, e por isso vive tipado aqui em vez de
 * virar um DTO por coluna de cada perna.
 */
final readonly class ResourceRowView
{
    /**
     * @param  array<string, scalar|null>  $attributes
     */
    public function __construct(
        public string $id,
        public ?string $status,
        public array $attributes = [],
        public ?AdvanceProjection $advance = null,
        public ?string $createdAt = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            ...$this->attributes,
            'advance' => $this->advance?->toArray(),
            'createdAt' => $this->createdAt,
        ];
    }
}
