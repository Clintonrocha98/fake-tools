<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * No máximo um cenário armado por perna da malha PIX — a unicidade de `leg`
     * é a regra, não uma otimização: armar de novo substitui o anterior em vez
     * de empilhar dois desfechos contraditórios para o mesmo próximo pedido.
     *
     * Tabela própria do fake-starkbank, sem nenhuma relação com a do
     * fake-binance: armar um desfecho aqui não pode alcançar o fluxo da venue.
     */
    public function up(): void
    {
        Schema::create('fake_starkbank_armed_scenarios', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('leg')->unique();
            $table->string('outcome');
            $table->jsonb('payload')->default('{}');
            $table->timestampTz('armed_at');
            $table->timestampsTz();
        });
    }
};
