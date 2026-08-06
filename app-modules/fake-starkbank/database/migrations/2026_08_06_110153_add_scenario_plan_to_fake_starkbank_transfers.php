<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * O destino que o cenário armado gravou no `POST /v2/transfer`. `held`
     * congela o avanço em `processing`; `destined_status` e `failure_reason`
     * carregam o desfecho de recusa até a leitura que o executa.
     */
    public function up(): void
    {
        Schema::table('fake_starkbank_transfers', static function (Blueprint $table): void {
            $table->string('destined_status')->nullable();
            $table->string('failure_reason')->nullable();
            $table->boolean('held')->default(value: false);
        });
    }
};
