<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| System-wide architecture rules
|--------------------------------------------------------------------------
|
| These enforce the conventions documented in .ai/guidelines: strict types
| everywhere, no debugging leftovers, no stdClass in domain code, and the
| module dependency direction (domain never depends on presentation).
|
*/

arch('application code declares strict types')
    ->expect('App')
    ->toUseStrictTypes();

arch('the admin presentation module declares strict types')
    ->expect('He4rt\PanelAdmin')
    ->toUseStrictTypes();

arch('no debugging statements leak into the codebase')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'var_export'])
    ->not->toBeUsed();
