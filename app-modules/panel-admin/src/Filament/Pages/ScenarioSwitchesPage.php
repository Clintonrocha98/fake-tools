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
use He4rt\FakeBinance\Scenarios\Actions\GetScenarioSwitchboard;
use He4rt\FakeBinance\Scenarios\Actions\ToggleScenarioSwitch;
use He4rt\FakeBinance\Scenarios\Enums\ScenarioSwitch;
use UnitEnum;

/**
 * Os três switches globais aplicados ANTES de qualquer endpoint da fake Binance
 * ({@see \He4rt\FakeBinance\Scenarios\Http\Middleware\ApplyScenarioSwitches}) —
 * um toggle por switch, sem clique nem confirmação, e o happy path nunca os
 * encontra ligados (persistem `false` por padrão).
 *
 * @property-read Schema $form
 */
class ScenarioSwitchesPage extends Page implements HasKnowledgeBase
{
    /** @var array<string, mixed> */
    public array $data = [];

    protected string $view = 'panel-admin::filament.pages.scenario-switches';

    protected static ?string $slug = 'scenario-switches';

    protected static ?string $title = null;

    protected static ?string $navigationLabel = null;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignalSlash;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::FakeBinance;

    protected static ?int $navigationSort = 5;

    /**
     * @return string[]
     */
    public static function getDocumentation(): array
    {
        return [
            'fake-binance.scenario-switches',
        ];
    }

    public static function getNavigationLabel(): string
    {
        return __('panel-admin::fake-binance.scenario_switches.title');
    }

    public function mount(): void
    {
        $switchboard = resolve(GetScenarioSwitchboard::class)->handle();

        $this->form->fill(collect(ScenarioSwitch::cases())
            ->mapWithKeys(fn (ScenarioSwitch $switch): array => [
                $switch->value => (bool) $switchboard->{$switch->column()},
            ])
            ->all());
    }

    public function getTitle(): string
    {
        return __('panel-admin::fake-binance.scenario_switches.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components(array_map(
                fn (ScenarioSwitch $switch): Toggle => Toggle::make($switch->value)
                    ->label($switch->getLabel())
                    ->helperText($switch->getDescription())
                    ->onColor('danger')
                    ->live()
                    ->afterStateUpdated(fn (bool $state) => $this->toggle($switch, $state)),
                ScenarioSwitch::cases(),
            ))
            ->statePath('data');
    }

    private function toggle(ScenarioSwitch $switch, bool $enabled): void
    {
        resolve(ToggleScenarioSwitch::class)->handle($switch, $enabled);

        Notification::make()
            ->title(__('panel-admin::fake-binance.scenario_switches.toggle_notification', [
                'switch' => $switch->getLabel(),
                'state' => __('panel-admin::fake-binance.scenario_switches.'.($enabled ? 'state_on' : 'state_off')),
            ]))
            ->success()
            ->send();
    }
}
