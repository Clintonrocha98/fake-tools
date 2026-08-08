<?php

declare(strict_types=1);

namespace He4rt\Control\Reset\Actions;

use He4rt\Control\Reset\DTOs\BaselineResetReport;
use He4rt\Control\Reset\Exceptions\ResetNotAllowedException;
use He4rt\FakeBinance\Database\Seeders\LedgerAccountSeeder;
use He4rt\FakeBinance\Deposit\Models\CryptoDeposit;
use He4rt\FakeBinance\Fiat\Models\FiatOrder;
use He4rt\FakeBinance\Fiat\Models\FiatWithdrawal;
use He4rt\FakeBinance\Ledger\Actions\CreditLedgerAccount;
use He4rt\FakeBinance\Ledger\Models\LedgerAccount;
use He4rt\FakeBinance\Scenarios\Models\ArmedScenario as VenueArmedScenario;
use He4rt\FakeBinance\Scenarios\Models\ScenarioSwitchboard as VenueSwitchboard;
use He4rt\FakeBinance\Spot\Models\SpotOrder;
use He4rt\FakeBinance\Support\BinanceLog;
use He4rt\FakeBinance\Withdraw\Models\Withdrawal;
use He4rt\FakeStarkbank\Brcode\Models\BrcodePayment;
use He4rt\FakeStarkbank\Database\Seeders\DictEntrySeeder;
use He4rt\FakeStarkbank\Dict\Models\DictEntry;
use He4rt\FakeStarkbank\Invoice\Models\Invoice;
use He4rt\FakeStarkbank\Scenarios\Models\ArmedScenario as PixArmedScenario;
use He4rt\FakeStarkbank\Scenarios\Models\ScenarioSwitchboard as PixSwitchboard;
use He4rt\FakeStarkbank\Support\StarkbankLog;
use He4rt\FakeStarkbank\Transfer\Models\Transfer;
use He4rt\FakeStarkbank\Webhook\Models\WebhookEmission;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * Devolve os dois fakes ao estado que um container recém-subido tem — nem mais,
 * nem menos.
 *
 * Um `db:seed` não serve: o `LedgerAccountSeeder` tem um guard de "já existe
 * alguma conta" que o faz não fazer nada com o ledger povoado. A diferença
 * entre este reset e o seed é exatamente **limpar antes de semear**.
 *
 * `control_events` sobrevive de propósito ({@see self::handle()}): quando o dev
 * reseta a baseline, quase sempre é para tentar de novo o que acabou de falhar,
 * e jogar fora o registro do que aconteceu destrói a evidência no momento em
 * que ela mais vale. A tela limpa que ele quer é a FRONTEIRA visível — o evento
 * de marco —, não a tabela vazia. O prune por idade cuida do volume.
 */
final readonly class ResetBaseline
{
    /**
     * As tabelas de cada fake, na ordem em que são limpas. `identity_*` e o
     * resto do monolito ficam intactos: o dev não pode perder o login do painel
     * ao resetar a baseline.
     *
     * @var array<string, list<class-string<Model>>>
     */
    private const array MODELOS = [
        'starkbank' => [
            WebhookEmission::class,
            Invoice::class,
            Transfer::class,
            BrcodePayment::class,
            DictEntry::class,
            PixArmedScenario::class,
            PixSwitchboard::class,
        ],
        'binance' => [
            FiatOrder::class,
            FiatWithdrawal::class,
            SpotOrder::class,
            Withdrawal::class,
            CryptoDeposit::class,
            LedgerAccount::class,
            VenueArmedScenario::class,
            VenueSwitchboard::class,
        ],
    ];

    public function __construct(
        private LedgerAccountSeeder $ledgerSeeder = new LedgerAccountSeeder,
        private DictEntrySeeder $dictSeeder = new DictEntrySeeder,
    ) {}

    public function handle(): BaselineResetReport
    {
        $this->guardEnvironment();

        $removidos = [];

        // Uma transação POR FAKE: um reset que falhe no meio não deixa metade de
        // uma malha limpa e a outra metade viva.
        foreach (self::MODELOS as $fake => $modelos) {
            $removidos += DB::transaction(function () use ($modelos): array {
                $contagem = [];

                foreach ($modelos as $modelo) {
                    $contagem[new $modelo()->getTable()] = $modelo::query()->delete();
                }

                return $contagem;
            });

            $this->seed($fake);
        }

        $relatorio = new BaselineResetReport($removidos, Date::now()->toIso8601String());

        // O marco sai pelos DOIS canais, e pela conexão dedicada do handler do
        // feed — então ele não some se alguma transação acima der rollback. É
        // ele que a sidebar usa para desenhar a fronteira entre rodadas.
        $contexto = ['removidos' => $relatorio->deleted, 'total' => $relatorio->total()];

        StarkbankLog::warning('fake-starkbank.control: baseline resetada — invoices, transfers, brcode payments e emissões apagados, DICT e cenários de volta ao estado semeado', $contexto);
        BinanceLog::warning('fake-binance.control: baseline resetada — ordens, saques e depósitos apagados, ledger de volta a seed_balances e cenários desarmados', $contexto);

        return $relatorio;
    }

    private function seed(string $fake): void
    {
        match ($fake) {
            'starkbank' => $this->dictSeeder->run(),
            // O seeder recebe a Action por injeção — o guard dele agora encontra
            // a tabela vazia, que é o que faz o reset diferir de um `db:seed`.
            'binance' => $this->ledgerSeeder->run(resolve(CreditLedgerAccount::class)),
            default => null,
        };
    }

    /**
     * Truncar tabelas merece cinto além do kill-switch.
     */
    private function guardEnvironment(): void
    {
        /** @var list<string> $permitidos */
        $permitidos = config()->array('control.reset.allowed_environments');
        $ambiente = (string) app()->environment();

        if (!in_array($ambiente, $permitidos, strict: true)) {
            throw ResetNotAllowedException::forEnvironment($ambiente, $permitidos);
        }
    }
}
