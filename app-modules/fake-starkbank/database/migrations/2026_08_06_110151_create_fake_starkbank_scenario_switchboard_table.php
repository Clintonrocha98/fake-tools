<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Linha única (singleton) com os switches globais da malha PIX — persiste
     * em banco e sobrevive a restart, ao contrário de um flag em memória. A
     * camada HTTP a consulta antes de qualquer endpoint `/v2/*`.
     *
     * Switchboard PRÓPRIO: `outage` armado aqui derruba só o fake-starkbank, e
     * é essa independência que justifica a tabela separada.
     */
    public function up(): void
    {
        Schema::create('fake_starkbank_scenario_switchboard', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->boolean('outage_mode')->default(value: false);
            $table->boolean('rate_limit_mode')->default(value: false);
            $table->unsignedInteger('rate_limit_retry_after_seconds')->default(30);
            $table->timestampsTz();
        });
    }
};
