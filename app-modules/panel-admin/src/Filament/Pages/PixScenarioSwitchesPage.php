<?php

declare(strict_types=1);

namespace He4rt\PanelAdmin\Filament\Pages;

use App\Enums\NavigationGroup;
use BackedEnum;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Guava\FilamentKnowledgeBase\Contracts\HasKnowledgeBase;
use He4rt\FakeStarkbank\Scenarios\Actions\GetScenarioSwitchboard;
use He4rt\FakeStarkbank\Scenarios\Actions\ToggleScenarioSwitch;
use He4rt\FakeStarkbank\Scenarios\Enums\PixScenarioSwitch;
use UnitEnum;

/**
 * Os dois switches globais aplicados ANTES de qualquer rota `/v2/*` do fake
 * StarkBank ({@see \He4rt\FakeStarkbank\Scenarios\Http\Middleware\ApplyPixScenarioSwitches})
 * — um toggle por switch, sem clique nem confirmação, e o happy path nunca os
 * encontra ligados (persistem `false` por padrão).
 *
 * Switchboard próprio: ligar outage aqui não alcança nenhuma rota do fake
 * Binance, que tem tela e tabela separadas.
 *
 * @property-read Schema $form
 */
class PixScenarioSwitchesPage extends Page implements HasKnowledgeBase
{
    /** @var array<string, mixed> */
    public array $data = [];

    protected string $view = 'panel-admin::filament.pages.pix-scenario-switches';

    protected static ?string $slug = 'pix-scenario-switches';

    protected static ?string $title = null;

    protected static ?string $navigationLabel = null;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignalSlash;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::FakeStarkbank;

    protected static ?int $navigationSort = 6;

    /**
     * @return string[]
     */
    public static function getDocumentation(): array
    {
        return [
            'fake-starkbank.pix-scenario-switches',
        ];
    }

    public static function getNavigationLabel(): string
    {
        return __('panel-admin::fake-starkbank.scenario_switches.title');
    }

    public function mount(): void
    {
        $switchboard = resolve(GetScenarioSwitchboard::class)->handle();

        $this->form->fill(collect(PixScenarioSwitch::cases())
            ->mapWithKeys(fn (PixScenarioSwitch $switch): array => [
                $switch->value => (bool) $switchboard->{$switch->column()},
            ])
            ->all());
    }

    public function getTitle(): string
    {
        return __('panel-admin::fake-starkbank.scenario_switches.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components(array_map(
                fn (PixScenarioSwitch $switch): Toggle => Toggle::make($switch->value)
                    ->label($switch->getLabel())
                    ->helperText($switch->getDescription())
                    ->onColor('danger')
                    ->live()
                    ->afterStateUpdated(fn (bool $state) => $this->toggle($switch, $state)),
                PixScenarioSwitch::cases(),
            ))
            ->statePath('data');
    }

    private function toggle(PixScenarioSwitch $switch, bool $enabled): void
    {
        resolve(ToggleScenarioSwitch::class)->handle($switch, $enabled);

        Notification::make()
            ->title(__('panel-admin::fake-starkbank.scenario_switches.toggle_notification', [
                'switch' => $switch->getLabel(),
                'state' => __('panel-admin::fake-starkbank.scenario_switches.'.($enabled ? 'state_on' : 'state_off')),
            ]))
            ->success()
            ->send();
    }
}
