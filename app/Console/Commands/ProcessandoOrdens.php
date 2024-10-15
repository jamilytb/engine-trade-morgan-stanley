<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Ordem;

class ProcessandoOrdens extends Command
{
    protected $signature = 'iniciar';
    protected $description = 'Processa ordens de compra ou venda';

    protected $ordemCompra;
    protected $ordemVenda;
    protected $precoAtivo = 50.00; // Preço fixo do ativo

    public function __construct()
    {
        parent::__construct();

        // Inicializa as ordens de compra e venda
        $this->ordemCompra = new Ordem();
        $this->ordemVenda = new Ordem();
    }

    public function handle()
    {
        while (true) {
            $this->info("\n=========  MENU  ==========");
            $this->info("Ativo: AAPL");
            $this->info("Preço: {$this->precoAtivo}\n");

            $this->info("1. Inserir order limit");
            $this->info("2. Inserir order market");
            $this->info("3. Exibir ordens de compra");
            $this->info("4. Exibir ordens de venda");
            $this->info("5. Sair");
            $choice = $this->ask('Escolha uma opção');

            switch ($choice) {
                case 1:
                    $this->inserirLimitOrder();
                    break;
                case 2:
                    $this->inserirMarketOrder();
                    break;
                case 3:
                    $this->mostrarOrdensCompra();
                    break;
                case 4:
                    $this->mostrarOrdensVenda();
                    break;
                case 5:
                    $this->info('Saindo...');
                    return;
                default:
                    $this->error('Opção inválida! Tente novamente.');
            }
        }
    }

    // Método para inserir uma ordem limit
    public function inserirLimitOrder()
    {
        $side = $this->choice('Digite o lado (buy/sell)', ['buy', 'sell']);
        $price = $this->ask('Digite o preço');
        $qty = $this->ask('Digite a quantidade');

        if ($side === 'buy') {
            $this->ordemCompra->atualizar($price, $qty);
            $this->info('Ordem limit de compra adicionada.');
            $this->executarLimitOrder('sell', $price, $qty);
        } elseif ($side === 'sell') {
            $this->ordemVenda->atualizar($price, $qty);
            $this->info('Ordem limit de venda adicionada.');
            $this->executarLimitOrder('buy', $price, $qty);
        }
    }

    // Método para inserir uma ordem de mercado e executar o trade imediatamente
    public function inserirMarketOrder()
    {
        // Solicita o lado da ordem (compra ou venda)
        $side = $this->choice('Digite o lado (buy/sell)', ['buy', 'sell']);
        $qty = $this->ask('Digite a quantidade'); // Solicita a quantidade

        // Executa o trade diretamente com o preço de R$ 50,00 (preço fixo)
        if ($side === 'buy') {
            // guardar o valor da ordem de compra
            $this->ordemCompra->atualizar($this->precoAtivo, $qty);
            $this->info("Trade, price: {$this->precoAtivo}, qty: {$qty}");
        } elseif ($side === 'sell') {
            // guardar o valor da ordem de venda
            $this->ordemVenda->atualizar($this->precoAtivo, $qty);
            $this->info("Trade, price: {$this->precoAtivo}, qty: {$qty}");
        }
    }

    // Método para exibir a ordem de compra
    public function mostrarOrdensCompra()
    {
        if ($this->ordemCompra->existe()) {
            $this->info('Ordens de compras realizadas:');
            $this->info("Price: {$this->ordemCompra->preco}, Qty: {$this->ordemCompra->quantidade}");
        } else {
            $this->info('Não há ordens de compra.');
        }
    }

    // Método para exibir a ordem de venda
    public function mostrarOrdensVenda()
    {
        if ($this->ordemVenda->existe()) {
            $this->info('Ordens de vendas realizadas:');
            $this->info("Price: {$this->ordemVenda->preco}, Qty: {$this->ordemVenda->quantidade}");
        } else {
            $this->info('Não há ordens de venda.');
        }
    }

    // Método para executar uma ordem limit com base nas ordens existentes
    public function executarLimitOrder($ladoOposto, $precoLimite, $qty)
    {
        if ($ladoOposto === 'sell' && $this->ordemVenda->existe()) {
            if ($this->ordemVenda->preco <= $precoLimite && $this->ordemVenda->quantidade >= $qty) {
                $this->info("Trade, price: {$this->ordemVenda->preco}, qty: {$qty}");
                $this->ordemVenda->limpar();
            }
        } elseif ($ladoOposto === 'buy' && $this->ordemCompra->existe()) {
            if ($this->ordemCompra->preco >= $precoLimite && $this->ordemCompra->quantidade >= $qty) {
                $this->info("Trade, price: {$this->ordemCompra->preco}, qty: {$qty}");
                $this->ordemCompra->limpar();
            }
        } else {
            $this->info('Não há ordens do lado oposto.');
        }
    }
}
