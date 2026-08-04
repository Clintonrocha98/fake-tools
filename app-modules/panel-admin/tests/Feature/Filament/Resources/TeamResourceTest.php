<?php

declare(strict_types=1);

namespace He4rt\PanelAdmin\Tests\Feature\Filament;

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use He4rt\Identity\Permissions\Roles;
use He4rt\Identity\Teams\Team;
use He4rt\Identity\Users\User;
use He4rt\PanelAdmin\Filament\Resources\Teams\Pages\EditTeam;
use He4rt\PanelAdmin\Filament\Resources\Teams\Pages\ListTeams;
use He4rt\PanelAdmin\Filament\Resources\Teams\RelationManagers\MembersRelationManager;
use He4rt\PanelAdmin\Filament\Resources\Teams\TeamResource;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    actingAs(User::factory()->create());

    // Give the user SuperAdmin role to bypass all policies
    auth()->user()->assignRole(Roles::SuperAdmin->value);
});

it('can list teams with translated columns', function (): void {
    $teams = Team::factory()->count(1)->create();

    expect(__('teams::filament.fields.name'))->toBe('Name')
        ->and(__('teams::filament.fields.status'))->toBe('Status');

    livewire(ListTeams::class)
        ->loadTable()
        ->assertCanSeeTableRecords($teams)
        ->assertSee(__('teams::filament.fields.name'))
        ->assertSee(__('teams::filament.fields.status'))
        ->assertSee(__('teams::filament.fields.members_count'));
});

it('can render create team page', function (): void {
    $this->get(TeamResource::getUrl('create'))
        ->assertSuccessful();
});

it('can render edit team page', function (): void {
    $team = Team::factory()->create();

    $this->get(TeamResource::getUrl('edit', ['record' => $team]))
        ->assertSuccessful();
});

it('can attach a member without a team_id sql error', function (): void {
    // Regression: Filament guessed the inverse relationship as `teams`, colliding
    // with Spatie HasRoles::teams() and querying a non-existent `team_id` column
    // while building the attach select options.
    $team = Team::factory()->create();
    $existingMember = User::factory()->create();
    $team->members()->attach($existingMember);

    $candidate = User::factory()->create();

    livewire(MembersRelationManager::class, [
        'ownerRecord' => $team,
        'pageClass' => EditTeam::class,
    ])
        ->callAction(TestAction::make('attach')->table(), ['recordId' => $candidate->id])
        ->assertHasNoActionErrors();

    expect($team->members()->whereKey($candidate->id)->exists())->toBeTrue();
});
