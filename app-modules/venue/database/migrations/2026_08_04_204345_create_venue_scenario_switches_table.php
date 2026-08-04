<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Linha única (singleton) que persiste os switches globais — sobrevive a
     * restart, ao contrário de um cache/flag em memória. A camada HTTP consulta
     * esta tabela antes de qualquer endpoint (ver ApplyScenarioSwitches).
     */
    public function up(): void
    {
        Schema::create('venue_scenario_switches', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->boolean('outage_mode')->default(value: false);
            $table->boolean('rate_limit_mode')->default(value: false);
            $table->unsignedInteger('rate_limit_retry_after_seconds')->default(30);
            $table->boolean('clock_skew_mode')->default(value: false);
            $table->timestampsTz();
        });
    }
};
