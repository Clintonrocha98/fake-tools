<?php

declare(strict_types=1);

use He4rt\FakeStarkbank\Workspace\Http\Controllers\ListWorkspacesController;
use Illuminate\Support\Facades\Route;

Route::middleware(['api', 'fake-starkbank.signed'])->get('/v2/workspace', ListWorkspacesController::class);
