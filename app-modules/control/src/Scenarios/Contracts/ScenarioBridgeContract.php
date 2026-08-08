<?php

declare(strict_types=1);

namespace He4rt\Control\Scenarios\Contracts;

use He4rt\Control\Scenarios\DTOs\LegView;
use He4rt\Control\State\DTOs\ArmedScenarioView;
use He4rt\Control\State\DTOs\SwitchboardView;

/**
 * A superfície de cenários de UM fake, vista pelo plano de controle.
 *
 * Isto NÃO unifica os subsistemas Scenarios: eles seguem separados de propósito
 * (armar `outage` no StarkBank não pode derrubar o fluxo Binance), com enums
 * próprios e Actions gêmeas de nome idêntico. O que existe aqui é uma interface
 * na camada de apresentação, e cada implementação importa só as Actions do seu
 * fake — nenhum código passa a ser compartilhado entre os dois.
 */
interface ScenarioBridgeContract
{
    /**
     * O segmento `{fake}` da rota que resolve para esta bridge.
     */
    public function fake(): string;

    /**
     * Toda perna do fake, com os desfechos que ela aceita e o que estiver
     * armado nela agora.
     *
     * @return list<LegView>
     */
    public function legs(): array;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function arm(string $leg, string $outcome, array $payload): ArmedScenarioView;

    public function disarm(string $leg): void;

    public function switchboard(): SwitchboardView;

    public function toggle(string $switch, bool $enabled): SwitchboardView;
}
