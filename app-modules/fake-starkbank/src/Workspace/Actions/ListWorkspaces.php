<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Workspace\Actions;

use Carbon\CarbonImmutable;
use He4rt\FakeStarkbank\Support\StarkbankLog;
use He4rt\FakeStarkbank\Workspace\DTOs\WorkspaceView;
use Throwable;

/**
 * Monta a única página de `GET /v2/workspace` a partir de
 * `fake-starkbank.workspace.*`. Workspace não tem ciclo de vida no domínio
 * StarkBank: nada em banco, nada a avançar, nenhum evento emitido — só config
 * lida a cada request, para que reconfigurar (status, allowedTaxIds) valha na
 * chamada seguinte sem reiniciar o processo.
 */
final readonly class ListWorkspaces
{
    /**
     * @return list<WorkspaceView>
     */
    public function handle(): array
    {
        $workspace = new WorkspaceView(
            id: (string) config('fake-starkbank.workspace.id', ''),
            username: (string) config('fake-starkbank.workspace.username', ''),
            name: (string) config('fake-starkbank.workspace.name', ''),
            allowedTaxIds: $this->allowedTaxIds(),
            status: (string) config('fake-starkbank.workspace.status', ''),
            organizationId: (string) config('fake-starkbank.workspace.organization_id', ''),
            pictureUrl: $this->pictureUrl(),
            created: $this->created(),
        );

        StarkbankLog::info('fake-starkbank.workspace: servindo o workspace de config em página única — o consumidor pagina até cursor null, então uma página que já encerra basta', [
            'workspace_id' => $workspace->id,
            'username' => $workspace->username,
            'status' => $workspace->status,
            'allowed_tax_ids' => $workspace->allowedTaxIds,
        ]);

        return [$workspace];
    }

    /**
     * @return list<string>
     */
    private function allowedTaxIds(): array
    {
        $configured = config('fake-starkbank.workspace.allowed_tax_ids', []);

        if (!is_array($configured)) {
            $configured = explode(',', (string) $configured);
        }

        $taxIds = [];

        foreach ($configured as $taxId) {
            $taxId = mb_trim((string) $taxId);

            if ($taxId !== '') {
                $taxIds[] = $taxId;
            }
        }

        return $taxIds;
    }

    private function pictureUrl(): ?string
    {
        $pictureUrl = mb_trim((string) config('fake-starkbank.workspace.picture_url', ''));

        return $pictureUrl === '' ? null : $pictureUrl;
    }

    /**
     * `created` sai sempre no formato dos fixtures (ISO-8601 com
     * microssegundos, offset explícito), qualquer que seja a forma em que a
     * config o carregue — o consumidor parseia com Carbon, mas um fake que
     * serve dois formatos conforme o env esconde drift de contrato.
     */
    private function created(): string
    {
        $configured = mb_trim((string) config('fake-starkbank.workspace.created', ''));

        try {
            $created = $configured === '' ? CarbonImmutable::now() : CarbonImmutable::parse($configured);
        } catch (Throwable) {
            StarkbankLog::warning('fake-starkbank.workspace: `created` configurado não é uma data parseável — servindo o instante atual para não derrubar a listagem', [
                'configured' => $configured,
            ]);

            $created = CarbonImmutable::now();
        }

        return $created->utc()->format('Y-m-d\TH:i:s.uP');
    }
}
