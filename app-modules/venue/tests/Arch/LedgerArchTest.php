<?php

declare(strict_types=1);

use App\Models\BaseModel;

/*
|--------------------------------------------------------------------------
| Ledger sub-domain architecture rules
|--------------------------------------------------------------------------
|
| Scoped to `He4rt\Venue\Ledger\Models` on purpose, not the whole `He4rt\Venue`
| namespace: a module-wide `toExtend(BaseModel::class)` would fail on non-Eloquent
| classes from other sub-domains (e.g. story/2's HMAC signing). Kept in its own file
| so this story's rule doesn't collide with story/2's own from-scratch VenueArchTest.
|
*/

arch('ledger models extend the shared BaseModel')
    ->expect('He4rt\Venue\Ledger\Models')
    ->toExtend(BaseModel::class);
