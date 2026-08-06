<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fake_starkbank_invoices', static function (Blueprint $table): void {
            // A pk é o id do StarkBank (string numérica de 16 dígitos), não um
            // uuid: é ele que viaja na wire, no brcode e na key de idempotência
            // do consumidor, e um id interno separado só criaria duas verdades.
            $table->string('id')->primary();
            $table->unsignedBigInteger('amount');
            $table->string('name');
            $table->string('tax_id');
            $table->string('status');
            $table->text('brcode');
            $table->jsonb('tags');
            // O relógio do vencimento; `expiration` são os segundos de graça
            // DEPOIS dele, nunca o prazo em si.
            $table->timestampTz('due');
            $table->unsignedInteger('expiration');
            $table->timestampTz('paid_at')->nullable();
            $table->timestampTz('expired_at')->nullable();
            // Cenário: enquanto true, o avanço lazy nunca calcula um novo status.
            $table->boolean('frozen')->default(value: false);
            $table->timestampsTz();

            // A varredura do extrato lê por status na ordem de criação, que é
            // também a ordem do cursor de paginação.
            $table->index(['status', 'created_at']);
        });
    }
};
