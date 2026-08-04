@php
/** @var \Laravel\Boost\Install\GuidelineAssist $assist */
@endphp

# Multi-Tenancy

The tenant unit is the **Team** (`He4rt\Identity\Teams\Team`). A `User` belongs to many Teams through the `identity_team_user` pivot.

## Membership & Filament contract

`User` mixes in `He4rt\Identity\Teams\Concerns\InteractsWithTenants`, which implements Filament's tenancy surface:

- `tenants()` — `BelongsToMany<Team>` over `identity_team_user`.
- `getTenants(Panel $panel)` — Teams the user may switch into.
- `canAccessTenant(Model $tenant)` — membership check.

Note: the Admin panel provider does **not** currently call `->tenant(Team::class)`. The Team-as-tenant plumbing lives on the model; wire the panel explicitly if/when a panel needs URL-slug tenant resolution.

## Data isolation

Tenant-scoped models carry a `team_id` FK and use the shared `App\Models\Concerns\BelongsToTeam` trait, which provides the `team()` BelongsTo relationship. There is **no** global tenant scope — scoping is explicit at the query site:

@verbatim
<code-snippet name="Explicit team scoping" lang="php">
ExternalIdentity::query()
    ->where('team_id', $team->id)
    ->get();
</code-snippet>
@endverbatim

## In tests

@verbatim
<code-snippet name="Team-scoped test setup" lang="php">
$team = Team::factory()->create();
$user = User::factory()->create();
$model = SomeModel::factory()->recycle($team)->recycle($user)->create();
</code-snippet>
@endverbatim

Use `->recycle($team)` to propagate the same `team_id` through factory chains.
