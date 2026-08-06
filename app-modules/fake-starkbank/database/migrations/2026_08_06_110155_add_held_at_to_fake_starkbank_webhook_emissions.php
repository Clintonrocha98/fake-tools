<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A emissão represada pelo desfecho `HoldNext`. O `ArmedScenario` é
     * consumido no instante da emissão, mas o efeito precisa sobreviver a esse
     * instante: é esta coluna, e não o cenário, que carrega o estado pendente
     * até o operador liberar.
     */
    public function up(): void
    {
        Schema::table('fake_starkbank_webhook_emissions', static function (Blueprint $table): void {
            $table->timestampTz('held_at')->nullable();
        });
    }
};
