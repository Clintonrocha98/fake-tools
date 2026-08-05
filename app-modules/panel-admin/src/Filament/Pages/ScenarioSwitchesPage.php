<?php

declare(strict_types=1);

namespace He4rt\PanelAdmin\Filament\Pages;

use App\Enums\NavigationGroup;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Guava\FilamentKnowledgeBase\Contracts\HasKnowledgeBase;
use He4rt\FakeBinance\Scenarios\Actions\GetScenarioSwitchboard;
use He4rt\FakeBinance\Scenarios\Actions\ToggleScenarioSwitch;
use He4rt\FakeBinance\Scenarios\Enums\ScenarioSwitch;
use He4rt\FakeBinance\Scenarios\Models\ScenarioSwitchboard;
use UnitEnum;

/**
 * Os três switches globais aplicados ANTES de qualquer endpoint da fake Binance
 * ({@see \He4rt\FakeBinance\Scenarios\Http\Middleware\ApplyScenarioSwitches}) — sem
 * clique, o happy path nunca os encontra ligados (persistem `false` por
 * padrão).
 */
class ScenarioSwitchesPage extends Page implements HasKnowledgeBase
{
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

    public function getTitle(): string
    {
        return __('panel-admin::fake-binance.scenario_switches.title');
    }

    public function getSwitchboard(): ScenarioSwitchboard
    {
        return resolve(GetScenarioSwitchboard::class)->handle();
    }

    /**
     * @return array<int, array{switch: ScenarioSwitch, enabled: bool}>
     */
    public function getSwitchRows(): array
    {
        $switchboard = $this->getSwitchboard();

        return collect(ScenarioSwitch::cases())
            ->map(fn (ScenarioSwitch $switch): array => [
                'switch' => $switch,
                'enabled' => (bool) $switchboard->{$switch->column()},
            ])
            ->all();
    }

    public function toggleAction(): Action
    {
        return Action::make('toggle')
            ->requiresConfirmation()
            ->modalHeading(function (array $arguments): string {
                /** @var array{switch: string, enable: bool} $arguments */
                $key = $arguments['enable'] ? 'turn_on' : 'turn_off';

                return __('panel-admin::fake-binance.scenario_switches.'.$key, [
                    'switch' => ScenarioSwitch::from($arguments['switch'])->getLabel(),
                ]);
            })
            ->action(function (array $arguments): void {
                /** @var array{switch: string, enable: bool} $arguments */
                $switch = ScenarioSwitch::from($arguments['switch']);
                $enable = $arguments['enable'];

                resolve(ToggleScenarioSwitch::class)->handle($switch, $enable);

                Notification::make()
                    ->title(__('panel-admin::fake-binance.scenario_switches.toggle_notification', [
                        'switch' => $switch->getLabel(),
                        'state' => __('panel-admin::fake-binance.scenario_switches.'.($enable ? 'state_on' : 'state_off')),
                    ]))
                    ->success()
                    ->send();
            });
    }
}
