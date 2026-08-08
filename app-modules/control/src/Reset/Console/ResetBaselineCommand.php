<?php

declare(strict_types=1);

namespace He4rt\Control\Reset\Console;

use He4rt\Control\Reset\Actions\ResetBaseline;
use He4rt\Control\Reset\Exceptions\ResetNotAllowedException;
use Illuminate\Console\Command;

/**
 * A casca de terminal sobre {@see ResetBaseline} — a mesma Action que
 * `POST /control/reset` invoca.
 *
 * Existe porque continua funcionando com `FAKE_TOOLS_CONTROL_ENABLED=false`,
 * que é justamente quando o HTTP não está disponível.
 */
final class ResetBaselineCommand extends Command
{
    protected $signature = 'control:reset-baseline';

    protected $description = 'Devolve os dois fakes ao estado semeado (o histórico do feed sobrevive)';

    public function handle(ResetBaseline $reset): int
    {
        try {
            $relatorio = $reset->handle();
        } catch (ResetNotAllowedException $resetNotAllowedException) {
            $this->error($resetNotAllowedException->getMessage());

            return self::FAILURE;
        }

        foreach ($relatorio->deleted as $tabela => $linhas) {
            $this->line(sprintf('%s: %d linha(s) removida(s).', $tabela, $linhas));
        }

        $this->info(sprintf('Baseline resetada — %d linha(s) no total. O feed foi preservado.', $relatorio->total()));

        return self::SUCCESS;
    }
}
