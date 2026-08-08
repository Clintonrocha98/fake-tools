<?php

declare(strict_types=1);

namespace He4rt\Control\State\DTOs;

final readonly class SwitchView
{
    public function __construct(
        public string $switch,
        public string $label,
        public bool $enabled,
    ) {}

    /**
     * @return array{switch: string, label: string, enabled: bool}
     */
    public function toArray(): array
    {
        return [
            'switch' => $this->switch,
            'label' => $this->label,
            'enabled' => $this->enabled,
        ];
    }
}
