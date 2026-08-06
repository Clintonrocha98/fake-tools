<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Workspace\DTOs;

use JsonSerializable;

/**
 * O shape EXATO de um item de `GET /v2/workspace`, no vocabulário de wire do
 * StarkBank — camelCase (`allowedTaxIds`, `organizationId`, `pictureUrl`), que
 * `WorkspaceCollectionResponse` do consumidor traduz para o snake_case interno
 * dele. `created` é ISO-8601 com microssegundos, o formato dos fixtures.
 *
 * `status` viaja como string crua, sem enum: o vocabulário observado tem um
 * único valor (`active`) e servir um valor diferente por config é justamente o
 * gancho de cenário do módulo — um enum fechado transformaria o cenário em erro.
 */
final readonly class WorkspaceView implements JsonSerializable
{
    /**
     * @param  list<string>  $allowedTaxIds
     */
    public function __construct(
        public string $id,
        public string $username,
        public string $name,
        public array $allowedTaxIds,
        public string $status,
        public string $organizationId,
        public ?string $pictureUrl,
        public string $created,
    ) {}

    /**
     * @return array{id: string, username: string, name: string, allowedTaxIds: list<string>, status: string, organizationId: string, pictureUrl: ?string, created: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'name' => $this->name,
            'allowedTaxIds' => $this->allowedTaxIds,
            'status' => $this->status,
            'organizationId' => $this->organizationId,
            'pictureUrl' => $this->pictureUrl,
            'created' => $this->created,
        ];
    }
}
