<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venue_ledger_accounts', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('asset')->unique();
            $table->decimal('free', 36, 18)->default(0);
            $table->decimal('locked', 36, 18)->default(0);
            $table->timestampsTz();
        });
    }
};
