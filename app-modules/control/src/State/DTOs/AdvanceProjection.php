<?php

declare(strict_types=1);

namespace He4rt\Control\State\DTOs;

/**
 * Quanto falta para o avanço lazy alcançar este registro — uma PROJEÇÃO, nunca
 * o resultado de chamar a Action de leitura que avança.
 *
 * É a resposta à dor que motivou o mapa: quem olha de fora não entende por que
 * o primeiro poll sempre vê zero, porque é a própria leitura que faz o tempo
 * passar. Aqui o dev vê o relógio sem mexer nele.
 *
 * Deliberadamente NÃO replica a máquina de estados de cada perna — isso seria
 * duas fontes de verdade divergindo. O que ela conhece é só o relógio: idade
 * contra o limiar configurado, e o que estiver bloqueando.
 */
final readonly class AdvanceProjection
{
    public function __construct(
        public bool $pending,
        public ?int $secondsUntil,
        public ?string $blockedBy,
    ) {}

    public static function blocked(string $motivo): self
    {
        return new self(pending: false, secondsUntil: null, blockedBy: $motivo);
    }

    public static function inSeconds(int $faltam): self
    {
        return new self(pending: $faltam <= 0, secondsUntil: max(0, $faltam), blockedBy: null);
    }

    /**
     * @return array{pending: bool, secondsUntil: int|null, blockedBy: string|null}
     */
    public function toArray(): array
    {
        return [
            'pending' => $this->pending,
            'secondsUntil' => $this->secondsUntil,
            'blockedBy' => $this->blockedBy,
        ];
    }
}
