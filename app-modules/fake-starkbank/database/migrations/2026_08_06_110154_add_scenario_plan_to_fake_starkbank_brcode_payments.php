<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * O destino que o cenário armado gravou no `POST /v2/brcode-payment`.
     * Mesmo desenho da transfer: `held` congela em `processing`,
     * `destined_status`/`failure_reason` carregam a recusa até a leitura.
     */
    public function up(): void
    {
        Schema::table('fake_starkbank_brcode_payments', static function (Blueprint $table): void {
            $table->string('destined_status')->nullable();
            $table->string('failure_reason')->nullable();
            $table->boolean('held')->default(value: false);
        });
    }
};
