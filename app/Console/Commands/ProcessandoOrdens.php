<?php

namespace App\Console\Commands;

use App\Models\Order;

use App\Models\OrderService;
use Illuminate\Console\Command;

class ProcessandoOrdens extends Command
{
    protected $signature = 'iniciar';
    protected $description = 'Executa a matching engine para ordens financeiras';

    protected OrderService $orderService;

    public function __construct()
    {
        parent::__construct();

        // Instancia o OrderService e adiciona ordens simuladas
        $this->orderService = new OrderService();
        $this->orderService->adicionarOrdem(new Order(tipo: 'limit', lado: 'buy', preco: 5.0, quantidade: 100));
        $this->orderService->adicionarOrdem(new Order(tipo: 'limit', lado: 'buy', preco: 4.90, quantidade: 150));
        $this->orderService->adicionarOrdem(new Order(tipo: 'limit', lado: 'buy', preco: 4.80, quantidade: 120));
        //
        $this->orderService->adicionarOrdem(new Order(tipo: 'limit', lado: 'sell', preco: 4.50, quantidade: 100));
        $this->orderService->adicionarOrdem(new Order(tipo: 'limit', lado: 'sell', preco: 4.55, quantidade: 200));
        $this->orderService->adicionarOrdem(new Order(tipo: 'limit', lado: 'sell', preco: 4.80, quantidade: 100));
    }

    public function handle(): void
    {
        while (true) {
            $this->info("\n====================  LIVRO DE ORDENS  =====================");
            $this->newLine();

            // Exibe as ordens
            $this->exibirOrdensCompra();
            $this->exibirOrdensVenda();

            $this->info("\n====================  MENU  ====================\n");
            $this->info("1. Inserir limit order");
            $this->info("2. Inserir market order");
            $this->info("3. Exibir histórico de ordens");
            $this->info("4. Cancelar ordem");
            $this->info("5. Alterar ordem");
            $this->info("6. Sair");

            $escolha = $this->ask('Escolha uma opção');

            switch ($escolha) {
                case 1:
                    $this->inserirOrdemLimit();
                    break;
                case 2:
                    $this->inserirOrdemMarket();
                    break;
                case 3:
                    $this->exibirHistorico();
                    break;
                case 4:
                    $this->cancelarOrdem();
                    break;
                case 5:
                    $this->alterarOrdem();
                    break;
                default:
                    $this->error("Opção inválida, tente novamente.");
            }
        }
    }

    // Menu 1
    protected function inserirOrdemLimit(): void
    {
        $lado = $this->ask('Informe o lado (buy/sell)');
        $preco = (float)$this->ask('Informe o preço');
        $quantidade = (int)$this->ask('Informe a quantidade');

        if (!in_array($lado, ['buy', 'sell'])) {
            $this->error('Lado inválido. Deve ser "buy" ou "sell".');
            return;
        }

        $ordem = new Order('limit', $lado, $preco, $quantidade);
       
        // Processa a ordem imediatamente após a inserção
        if ($lado === 'buy') {
            $this->orderService->processarOrdemCompra($ordem);
        } else {
            $this->orderService->processarOrdemVenda($ordem);
        }

        $this->info("Limit order inserida com sucesso!");
    }

    // Menu 2
    protected function inserirOrdemMarket(): void
    {
        $lado = $this->ask('Informe o lado (buy/sell)');
        $quantidade = (int) $this->ask('Informe a quantidade');

        if (!in_array($lado, ['buy', 'sell'])) {
            $this->error('Lado inválido');
            return;
        }

        // Cria uma nova ordem Market
        $ordem = new Order('market', $lado, null, $quantidade);

        // Processa a ordem market no OrderService
        $this->orderService->processarOrdemMarket($ordem);

        $this->info("Market order processada com sucesso!");
    }

    // Menu 3
    protected function exibirHistorico(): void
    {
        $historico = $this->orderService->HistoricoOrdenado();

        $this->info("===== Histórico =====");
        foreach ($historico as $ordem) {
            $this->line("{$ordem['id']} {$ordem['tipo']} {$ordem['lado']} {$ordem['quantidade']} @ {$ordem['preco']}");
        }
        $this->newLine();
    }

    // Menu 4
    protected function cancelarOrdem(): void
    {
        $id = $this->ask("Informe o ID da ordem que deseja cancelar");

        if ($this->orderService->cancelarOrdem($id)) {
            $this->info("Ordem com ID {$id} cancelada com sucesso.");
        } else {
            $this->error("A ordem com ID {$id} não pode ser cancelada. Somente ordens limit não executadas são canceláveis.");
        }
    }

    // Menu 5
    protected function alterarOrdem(): void
    {
        $id = $this->ask("Informe o ID da ordem que deseja alterar");
        $ordem = $this->orderService->OrdemId($id);

        if (!$ordem) {
            $this->error("A ordem com ID {$id} não foi encontrada.");
            return;
        }

        if ($ordem['tipo'] !== 'limit') {
            $this->error("A ordem com ID {$id} não pode ser alterada. Somente ordens limit são alteráveis.");
            return;
        }

        $this->line("Ordem encontrada: ID {$id}, Tipo: {$ordem['tipo']}, Lado: {$ordem['lado']}, Preço: {$ordem['preco']}, Quantidade: {$ordem['quantidade']}");

        $campo = $this->ask("Deseja alterar 'preço' ou 'quantidade'?");
        if (!in_array($campo, ['preco', 'quantidade'])) {
            $this->error("Opção inválida. Informe 'preço' ou 'quantidade'.");
            return;
        }

        $novoValor = $this->ask("Informe o novo valor para {$campo}");
        $resultado = $this->orderService->alterarOrdem($id, $campo, $novoValor);

        if ($resultado) {
            $this->line("{$campo} da ordem com ID {$id} alterado para {$novoValor} com sucesso.");
        } else {
            $this->error("Não foi possível alterar a ordem.");
        }
    }

    // Mostar simulações de ordens de compra e venda
    protected function exibirOrdensCompra(): void
    {
        $this->info("===== Ordens de Compra =====");
        $this->orderService->OrdensCompra()->each(function ($ordem) {
            $this->line("Quantidade: {$ordem->quantidade}, Preço: {$ordem->preco}");
        });
        $this->newLine();
    }

    protected function exibirOrdensVenda(): void
    {
        $this->info("===== Ordens de Venda =====");
        $this->orderService->OrdensVenda()->each(function ($ordem) {
            $this->line("Quantidade: {$ordem->quantidade}, Preço: {$ordem->preco}");
        });
        $this->newLine();
    }
}
