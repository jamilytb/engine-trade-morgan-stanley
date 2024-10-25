<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class ProcessandoOrdensV1 extends Command
{
    protected $signature = 'iniciar';
    protected $description = 'Executa a matching engine para ordens financeiras';

    protected Collection $buyOrders;
    protected Collection $sellOrders;
    protected Collection $minhasCompras;

    public function __construct()
    {
        parent::__construct();

        // Inicializando coleções de ordens
        $this->buyOrders = new Collection();
        $this->sellOrders = new Collection();
        $this->minhasCompras = new Collection();

        // Ordens predefinidas
        $this->buyOrders->push($this->criarOrdem(id: 1, tipo: 'limit', lado: 'buy', preco: 5.0, quantidade: 100));
        $this->buyOrders->push($this->criarOrdem(id: 2, tipo: 'limit', lado: 'buy', preco: 4.90, quantidade: 150));
        $this->buyOrders->push($this->criarOrdem(id: 3, tipo: 'limit', lado: 'buy', preco: 4.80, quantidade: 120));

        $this->sellOrders->push($this->criarOrdem(id: 4, tipo: 'limit', lado: 'sell', preco: 4.50, quantidade: 100));
        $this->sellOrders->push($this->criarOrdem(id: 5, tipo: 'limit', lado: 'sell', preco: 4.55, quantidade: 200));
        $this->sellOrders->push($this->criarOrdem(id: 6, tipo: 'limit', lado: 'sell', preco: 4.80, quantidade: 100));

        $this->ordenarOrdens();
    }

    public function handle(): int
    {
        while (true) {
            $this->info("\n====================  LIVRO DE ORDENS  =====================");
            $this->exibirOrdensCompra();
            $this->exibirOrdensVenda();

            $this->info("\n====================  MENU  ====================\n");
            $this->info("1. Inserir ordem limit");
            $this->info("2. Inserir ordem market");
            $this->info("3. Exibir ordens de compra");
            $this->info("4. Exibir ordens de venda");
            $this->info("5. Sair");

            $escolha = $this->ask('Escolha uma opção');

            switch ($escolha) {
                case 1:
                    $this->inserirOrdemLimit();
                    break;
                case 2:
                    $this->inserirOrdemMarket();
                    break;
                case 3:
                    $this->exibirHistorico('buy');
                    break;
                case 4:
                    $this->exibirHistorico('sell');
                    break;
                case 5:
                    $this->info("Saindo...");
                    return Command::SUCCESS;
                default:
                    $this->error("Opção inválida, tente novamente.");
            }
        }
    }

    // Cria uma nova ordem com os parâmetros fornecidos
    protected function criarOrdem(int $id, string $tipo, string $lado, ?float $preco, int $quantidade): object
    {
        return (object)[
            'id' => $id,
            'tipo' => $tipo,
            'lado' => $lado,
            'preco' => $preco,
            'quantidade' => $quantidade
        ];
    }

    // Insere uma nova ordem limit, coletando dados do usuário
    protected function inserirOrdemLimit(): void
    {
        $lado = $this->ask('Informe o lado (buy/sell)');
        $preco = $this->ask('Informe o preço');
        $quantidade = $this->ask('Informe a quantidade');

        if (!in_array($lado, ['buy', 'sell'])) {
            $this->error('Lado inválido. Deve ser "buy" ou "sell".');
            return;
        }

        $ordem = (object)[
            'id' => uniqid(),
            'tipo' => 'limit',
            'lado' => $lado,
            'preco' => (float)$preco,
            'quantidade' => (int)$quantidade
        ];

        $this->adicionarOrdem($ordem);
        $this->info("Ordem limit inserida com sucesso!");
    }

    protected function inserirOrdemMarket(): void
    {
        $lado = $this->ask('Informe o lado (buy/sell)');
        $quantidade = $this->ask('Informe a quantidade');

        if (!in_array($lado, ['buy', 'sell'])) {
            $this->error('Lado inválido. Deve ser "buy" ou "sell".');
            return;
        }

        $ordem = (object)[
            'id' => uniqid(),
            'tipo' => 'market',
            'lado' => $lado,
            'quantidade' => (int)$quantidade
        ];

        $this->processarOrdemMarket($ordem);
        $this->info("Ordem market processada com sucesso!");
    }

    protected function processarOrdemMarket(object $ordem): void
    {
        if ($ordem->lado === 'buy') {
            $this->processarOrdemCompra($ordem);
        } else {
            $this->processarOrdemVenda($ordem);
        }
    }

    // Adiciona uma ordem e decide se é uma compra ou venda
    protected function adicionarOrdem(object $ordem): void
    {
        if ($ordem->lado === 'buy') {
            $this->processarOrdemCompra($ordem);
        } else {
            $this->processarOrdemVenda($ordem);
        }
    }

    // Processa a ordem de compra, casando-a com várias ordens de venda, até que a quantidade seja atendida
    protected function processarOrdemCompra(object $ordem): void
    {
        while ($ordem->quantidade > 0) {
            // Pesquisa o menor preço na coleção de ordens de venda
            $sellOrder = $this->sellOrders->sortBy('preco')->first();

            // Se não houver mais ordens de venda, interrompe o processo
            if (!$sellOrder) {
                break;
            }

            // Em ordens market, ignoramos a verificação de preço e executamos com o melhor preço disponível
            if ($ordem->tipo === 'limit' && $ordem->preco < $sellOrder->preco) {
                $this->guardarHistorico($ordem->id, $ordem->lado, $ordem->preco, $ordem->quantidade, $ordem->tipo);
                break;
            }

            // Remover a quantidade da ordem de venda
            $quantidadePreenchida = min($ordem->quantidade, $sellOrder->quantidade);
            $ordem->quantidade -= $quantidadePreenchida;
            $sellOrder->quantidade -= $quantidadePreenchida;

            // Exibe o trade realizado
            $this->guardarHistorico($ordem->id, $ordem->lado, $sellOrder->preco, $quantidadePreenchida, $ordem->tipo);
            $this->exibirTrade($sellOrder->preco, $quantidadePreenchida);

            // Se a quantidade da ordem de venda for 0, remove a ordem de venda
            if ($sellOrder->quantidade === 0) {
                $this->sellOrders = $this->sellOrders->reject(function ($value) use ($sellOrder) {
                    return $value->id == $sellOrder->id;
                });
            }
        }

        // Ordena as ordens de compra
        $this->ordenarOrdens();
    }

    // Processa a ordem de venda, casando-a com várias ordens de compra, até que a quantidade seja atendida
    protected function processarOrdemVenda(object $ordem): void
    {
        while ($ordem->quantidade > 0) {
            // Pesquisa o maior preço na coleção de ordens de compra
            $buyOrder = $this->buyOrders->sortByDesc('preco')->first();

            // Se não houver mais ordens de compra, interrompe o processo
            if (!$buyOrder) {
                break;
            }

            // Em ordens market, ignoramos a verificação de preço e executamos com o melhor preço disponível
            if ($ordem->tipo === 'limit' && $ordem->preco > $buyOrder->preco) {
                $this->guardarHistorico($ordem->id, $ordem->lado, $ordem->preco, $ordem->quantidade, $ordem->tipo);
                break;
            }

            // Remover a quantidade da ordem de compra
            $quantidadePreenchida = min($ordem->quantidade, $buyOrder->quantidade);
            $ordem->quantidade -= $quantidadePreenchida;
            $buyOrder->quantidade -= $quantidadePreenchida;

            // Exibe o trade realizado
            $this->guardarHistorico($ordem->id, $ordem->lado, $buyOrder->preco, $quantidadePreenchida, $ordem->tipo);
            $this->exibirTrade($buyOrder->preco, $quantidadePreenchida);

            // Se a quantidade da ordem de compra for 0, remove a ordem de compra
            if ($buyOrder->quantidade === 0) {
                $this->buyOrders = $this->buyOrders->reject(function ($value) use ($buyOrder) {
                    return $value->id == $buyOrder->id;
                });
            }
        }

        // Ordena as ordens de venda
        $this->ordenarOrdens();
    }

    // Exibe o trade realizado
    protected function exibirTrade(?float $preco, int $quantidade): void
    {
        echo "Trade, price: {$preco}, qty: {$quantidade}\n";
    }

    // Ordena as ordens de compra e venda
    protected function ordenarOrdens(): void
    {
        $this->buyOrders = $this->buyOrders->sortByDesc('preco')->values();
        $this->sellOrders = $this->sellOrders->sortBy('preco')->values();
    }

    // Exibe as ordens de compra no console
    protected function exibirOrdensCompra(): void
    {
        $this->info("\n===== Ordens de Compra =====\n");
        foreach ($this->buyOrders as $order) {
            $this->line(" - Quantidade: {$order->quantidade}, Preço: {$order->preco}");
        }
    }

    // Exibe as ordens de venda no console
    protected function exibirOrdensVenda(): void
    {
        $this->info("\n===== Ordens de Venda =====\n");
        foreach ($this->sellOrders as $order) {
            $this->line(" - Quantidade: {$order->quantidade}, Preço: {$order->preco}");
        }
    }

    protected function exibirHistorico(string $lado): void
    {
        $this->info("\n===== Histórico =====\n");
        foreach ($this->minhasCompras as $trade) {

            // mostra apenas os trades do lado informado
            if ($trade['lado'] !== $lado) {

                continue;
            }



            $this->line(" {$trade['id']} {$trade['tipo']} {$trade['lado']} {$trade['quantidade']} @ {$trade['preco']}");
        }
    }

    // Guarda a compra no histórico (Collection minhasCompras)
    protected function guardarHistorico(string $id, string $lado, float $preco, int $quantidade, string $tipo): void
    {
        $this->minhasCompras->push([
            'id' => $id,
            'tipo' => $tipo,
            'lado' => $lado,
            'quantidade' => $quantidade,
            'preco' => $preco
        ]);
    }
}
