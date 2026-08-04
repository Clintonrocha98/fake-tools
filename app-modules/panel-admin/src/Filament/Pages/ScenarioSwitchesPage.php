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
use He4rt\Venue\Scenarios\Actions\GetScenarioSwitchboard;
use He4rt\Venue\Scenarios\Actions\ToggleScenarioSwitch;
use He4rt\Venue\Scenarios\Enums\ScenarioSwitch;
use He4rt\Venue\Scenarios\Models\ScenarioSwitchboard;
use UnitEnum;

/**
 * Os três switches globais aplicados ANTES de qualquer endpoint do venue
 * ({@see \He4rt\Venue\Scenarios\Http\Middleware\ApplyScenarioSwitches}) — sem
 * clique, o happy path nunca os encontra ligados (persistem `false` por
 * padrão).
 */
class ScenarioSwitchesPage extends Page implements HasKnowledgeBase
{
    protected string $view = 'panel-admin::filament.pages.scenario-switches';

    protected static ?string $slug = 'scenario-switches';

    protected static ?string $title = 'Scenario Switches';

    protected static ?string $navigationLabel = 'Scenario Switches';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignalSlash;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Venue;

    protected static ?int $navigationSort = 5;

    /**
     * @return string[]
     */
    public static function getDocumentation(): array
    {
        return [
            'venue.scenario-switches',
        ];
    }

    public function getSwitchboard(): ScenarioSwitchboard
    {
        return resolve(GetScenarioSwitchboard::class)();
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
                return sprintf(
                    '%s the %s switch?',
                    $arguments['enable'] ? 'Turn on' : 'Turn off',
                    ScenarioSwitch::from($arguments['switch'])->getLabel(),
                );
            })
            ->action(function (array $arguments): void {
                /** @var array{switch: string, enable: bool} $arguments */
                $switch = ScenarioSwitch::from($arguments['switch']);
                $enable = $arguments['enable'];

                resolve(ToggleScenarioSwitch::class)($switch, $enable);

                Notification::make()
                    ->title(sprintf('%s is now %s', $switch->getLabel(), $enable ? 'ON' : 'OFF'))
                    ->success()
                    ->send();
            });
    }
}
