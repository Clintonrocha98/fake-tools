<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * O destino que o cenário armado gravou na criação. A invoice de perna
     * assíncrona consome o `ArmedScenario` UMA vez, no `POST`, e nasce
     * destinada: as leituras seguintes só executam o que já está na linha, sem
     * voltar a tocar a tabela de cenários.
     */
    public function up(): void
    {
        Schema::table('fake_starkbank_invoices', static function (Blueprint $table): void {
            $table->string('destined_status')->nullable();
            $table->unsignedInteger('extra_advance_seconds')->default(0);
        });
    }
};
