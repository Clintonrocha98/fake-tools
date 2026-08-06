<?php

declare(strict_types=1);

namespace He4rt\PanelAdmin\Filament\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use He4rt\FakeStarkbank\Scenarios\Actions\ArmScenario;
use He4rt\FakeStarkbank\Scenarios\Actions\DisarmScenario;
use He4rt\FakeStarkbank\Scenarios\Contracts\PixLegOutcomeContract;
use He4rt\FakeStarkbank\Scenarios\DTOs\PixScenarioPayload;
use He4rt\FakeStarkbank\Scenarios\Enums\PixLeg;

/**
 * "Armar próximo" no cabeçalho da listagem de uma perna: o mesmo conjunto de
 * desfechos da página de cenários, oferecido onde o operador já está olhando.
 *
 * Embrulha as Actions de domínio ({@see ArmScenario} / {@see DisarmScenario}) —
 * a decisão de qual perna e qual desfecho é da UI, o efeito é do módulo.
 *
 * Diferente da página, aqui o desfecho é um `Select` e não um switch: a
 * listagem não tem espaço para um card por perna, e a ação é modal — o "nada
 * armado" da lista é o desarme explícito, que o switch resolveria desligando.
 */
final class ArmPixLegAction
{
    public static function make(PixLeg $leg): Action
    {
        return Action::make('armNextPixLeg')
            ->label(__('panel-admin::fake-starkbank.armed_scenarios.arm_next'))
            ->icon(Heroicon::OutlinedBoltSlash)
            ->color('danger')
            ->modalDescription(__('panel-admin::fake-starkbank.armed_scenarios.arm_next_description'))
            ->schema(self::schema($leg))
            ->action(function (array $data) use ($leg): void {
                /** @var array{outcome?: string|null, reason?: string|null, extraSeconds?: string|null} $data */
                self::apply($leg, $data);

                Notification::make()
                    ->title(__('panel-admin::fake-starkbank.armed_scenarios.arm_next_notification'))
                    ->success()
                    ->send();
            });
    }

    /**
     * @return array<int, Select|TextInput>
     */
    private static function schema(PixLeg $leg): array
    {
        $components = [
            Select::make('outcome')
                ->label(__('panel-admin::fake-starkbank.armed_scenarios.outcome_field'))
                ->options(self::outcomeOptions($leg))
                ->placeholder(__('panel-admin::fake-starkbank.armed_scenarios.disarm_option'))
                ->live(),
        ];

        foreach ([
            'reason' => 'panel-admin::fake-starkbank.armed_scenarios.reason',
            'extraSeconds' => 'panel-admin::fake-starkbank.armed_scenarios.extra_seconds',
        ] as $field => $label) {
            if (!self::legUses($leg, $field)) {
                continue;
            }

            $components[] = TextInput::make($field)
                ->label(__($label))
                // O campo só aparece quando o desfecho escolhido o usa: um
                // motivo ao lado de "segurar em processing" seria texto que
                // nada lê.
                ->visible(fn (Get $get): bool => self::outcomeUses($leg, $get('outcome'), $field));
        }

        return $components;
    }

    /**
     * @param  array{outcome?: string|null, reason?: string|null, extraSeconds?: string|null}  $data
     */
    private static function apply(PixLeg $leg, array $data): void
    {
        $outcome = self::resolveOutcome($leg, $data['outcome'] ?? null);

        if (!$outcome instanceof PixLegOutcomeContract) {
            resolve(DisarmScenario::class)->handle($leg);

            return;
        }

        resolve(ArmScenario::class)->handle($outcome, PixScenarioPayload::fromArray([
            'reason' => $data['reason'] ?? null,
            'extraSeconds' => $data['extraSeconds'] ?? null,
        ]));
    }

    /**
     * @return array<string, string>
     */
    private static function outcomeOptions(PixLeg $leg): array
    {
        $options = [];

        foreach ($leg->outcomes() as $outcome) {
            $options[(string) $outcome->value] = $outcome->getLabel();
        }

        return $options;
    }

    private static function resolveOutcome(PixLeg $leg, mixed $value): ?PixLegOutcomeContract
    {
        return is_string($value) && $value !== '' ? $leg->outcomeFrom($value) : null;
    }

    private static function outcomeUses(PixLeg $leg, mixed $value, string $field): bool
    {
        $outcome = self::resolveOutcome($leg, $value);

        return $outcome instanceof PixLegOutcomeContract
            && in_array($field, $outcome->payloadFields(), strict: true);
    }

    private static function legUses(PixLeg $leg, string $field): bool
    {
        return array_any($leg->outcomes(), fn (PixLegOutcomeContract $outcome) => in_array($field, $outcome->payloadFields(), strict: true));
    }
}
