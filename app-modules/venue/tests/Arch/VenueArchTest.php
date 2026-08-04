<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Venue module architecture rules
|--------------------------------------------------------------------------
|
| Module-wide conventions shared by every sub-domain of `He4rt\Venue`. Rules scoped to
| a single sub-domain (e.g. the ledger models) live in their own Arch test file —
| story/2 and story/3 both scaffold this file from scratch, so keeping it to only the
| rules every wave agrees on avoids an add/add conflict on merge.
|
*/

arch('the venue domain module declares strict types')
    ->expect('He4rt\Venue')
    ->toUseStrictTypes();

arch('domain code never produces a stdClass')
    ->expect('He4rt\Venue')
    ->not->toUse('stdClass');

arch('domain modules do not depend on the presentation layer')
    ->expect('He4rt\Venue')
    ->not->toUse('He4rt\PanelAdmin');
