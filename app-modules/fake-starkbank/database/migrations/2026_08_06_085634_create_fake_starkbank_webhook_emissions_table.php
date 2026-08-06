<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * O livro-caixa das emissões de webhook: uma linha por envelope montado,
     * gravada ANTES da entrega. `event_id` é único porque é a chave de
     * idempotência do consumidor — um replay reusa a mesma linha (mesmo body,
     * mesma signature), nunca cria outra.
     *
     * `payload` guarda o body EXATO enviado, não uma reconstrução: o replay
     * precisa ser byte a byte, e um `json_encode` diferente do que foi assinado
     * quebra a verificação mesmo com dados idênticos.
     */
    public function up(): void
    {
        Schema::create('fake_starkbank_webhook_emissions', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('event_id')->unique();
            $table->string('subscription');
            $table->string('event_type');
            $table->string('entity_id');
            $table->string('url');
            $table->jsonb('payload');
            $table->string('signature');
            $table->integer('response_code')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->string('failed_reason')->nullable();
            $table->timestampsTz();

            // O flush varre exatamente por isto: pendentes, mais antigas primeiro.
            $table->index(['sent_at', 'created_at']);
        });
    }
};
