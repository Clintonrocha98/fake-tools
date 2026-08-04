<?php

declare(strict_types=1);

namespace He4rt\PanelAdmin\Filament\Resources\Teams\RelationManagers;

use Filament\Actions\AttachAction;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DetachAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use He4rt\PanelAdmin\Filament\Resources\Users\UserResource;
use Illuminate\Database\Eloquent\Model;

class MembersRelationManager extends RelationManager
{
    protected static string $relationship = 'members';

    /**
     * The User → Team relationship name.
     *
     * Set explicitly so Filament's AttachAction does not fall back to guessing
     * the inverse as `teams` (plural of the parent model). That guess collides
     * with Spatie's `HasRoles::teams()` stub relationship, which builds invalid
     * SQL referencing `identity_model_has_roles.team_id` — a column that only
     * exists when the permission teams feature is enabled (it is disabled here).
     */
    protected static ?string $inverseRelationship = 'tenants';

    protected static ?string $relatedResource = UserResource::class;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('teams::filament.relation_managers.members.title');
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                DetachAction::make(),
            ])
            ->headerActions([
                CreateAction::make(),
                AttachAction::make()
                    ->preloadRecordSelect(),
            ]);
    }

    protected static function getModelLabel(): ?string
    {
        return __('teams::filament.relation_managers.members.label');
    }
}
