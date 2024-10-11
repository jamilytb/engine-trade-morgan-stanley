<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\OrderBook;

class ProcessandoOrdens extends Command
{
    protected $signature = 'iniciar';
    protected $description = 'Processa ordens de compra ou venda em um loop contínuo';

    public function handle()
    {
        while (true) {
            $this->info("\n=========  MENU  ==========");
            $this->info("Ativo: AAPL");
            $this->info("Price: 50.00\n");

            $this->info("1. Inserir order limit");
            $this->info("2. Inserir order market");
            $this->info("3. Exibir ordens de compra");
            $this->info("4. Exibir ordens de venda");
            $this->info("5. Sair");
            $choice = $this->ask('Escolha uma opção');

            switch ($choice) {
                case 1:
                    $this->inserindoLimitOrder();
                    break;
                case 2:
                    $this->inserindoMarketOrder();
                    break;
                case 3:
                    $this->mostrandoOrdensCompra();
                    break;
                case 4:
                    $this->mostrandoOrdensVenda();
                    break;
                case 5:
                    $this->info('Saindo...');
                    return;
                default:
                    $this->error('Opção inválida! Tente novamente.');
            }
        }
    }

    public function inserindoLimitOrder()
    {
        $side = $this->choice(
            'Digite o lado (buy/sell)',
            ['buy', 'sell']
        );

        $price = $this->ask('Digite o preço');
        $qty = $this->ask('Digite a quantidade');

        if ($side === 'buy') {
            // Adiciona ordem de compra ao livro
            OrderBook::adicionarOrdemCompra($price, $qty);
            $this->info('Order limit de compra adicionada com sucesso.');

            // Tenta executar a ordem com base nas ordens de venda existentes
            $this->executandoLimitOrder('sell', $price, $qty);
        } elseif ($side === 'sell') {
            // Adiciona ordem de venda ao livro
            OrderBook::adicionarOrdemVenda($price, $qty);
            $this->info('Order limit de venda adicionada com sucesso.');

            // Tenta executar a ordem com base nas ordens de compra existentes
            $this->executandoLimitOrder('buy', $price, $qty);
        } else {
            $this->error('Lado inválido! Use "buy" ou "sell".');
        }
    }

    public function inserindoMarketOrder()
    {
        $side = $this->choice(
            'Digite o lado (buy/sell)',
            ['buy', 'sell']
        );

        $qty = $this->ask('Digite a quantidade');
        $precoMarket = OrderBook::obterPrecoAtivo(); 

        if ($side === 'buy') {
            // Tenta executar a ordem de compra usando ordens de venda
            $qtySelecionada = $this->executandoTrade('sell', $qty);

            // Se não houver ordens do lado oposto, adiciona ao livro
            if ($qtySelecionada === 0) {
                OrderBook::adicionarOrdemCompra($precoMarket, $qty);
            }
        } elseif ($side === 'sell') {
            // Tenta executar a ordem de venda usando ordens de compra
            $qtySelecionada = $this->executandoTrade('buy', $qty);

            // Se não houver ordens do lado oposto, adiciona ao livro
            if ($qtySelecionada === 0) {
                OrderBook::adicionarOrdemVenda($precoMarket, $qty);
            }
        } else {
            $this->error('Inválido! Use "buy" ou "sell".');
            return;
        }

        // Exibe o trade com o preço de mercado
        $this->info("Trade, price: {$precoMarket}, qty: {$qty}");
    }

    public function mostrandoOrdensCompra()
    {
        $compraOrdem = OrderBook::ordemCompra();
        if (empty($compraOrdem)) {
            $this->info('Não há ordens de compra.');
        } else {
            $this->info("Ordens de compra:");
            foreach ($compraOrdem as $order) {
                $this->info("Price: {$order['price']}, Qty: {$order['qty']}");
            }
        }
    }

    public function mostrandoOrdensVenda()
    {
        $vendeOrdem = OrderBook::ordemVenda();
        if (empty($vendeOrdem)) {
            $this->info('Não há ordens de venda.');
        } else {
            $this->info("Ordens de venda:");
            foreach ($vendeOrdem as $order) {
                $this->info("Price: {$order['price']}, Qty: {$order['qty']}");
            }
        }
    }

    public function executandoTrade($ladoOposto, $qty)
    {
        $price = OrderBook::obterPrecoAtivo(); // Preço fixo do ativo
        $qtySelecionada = 0;

        // Se a ordem for de compra
        if ($ladoOposto === 'sell') {
            $ordem = OrderBook::ordemVenda(); // Ordens de venda
        } else {
            $ordem = OrderBook::ordemCompra(); // Ordens de compra
        }

        // Tentar preencher as ordens se existirem
        if (!empty($ordem)) {
            // Iterar pelas ordens até preencher a quantidade necessária
            foreach ($ordem as $index => $order) {
                if ($order['qty'] >= $qty) {
                    // Trade
                    $qtySelecionada = $qty;
                    $order['qty'] -= $qty;

                    // Remove a ordem se a quantidade for 0
                    if ($order['qty'] == 0) {
                        unset($ordem[$index]);
                    }
                    break; // Saímos do loop após preencher a ordem
                } else {
                    // Trade
                    $qtySelecionada += $order['qty'];
                    $qty -= $order['qty'];
                    unset($ordem[$index]);
                }
            }

            // Atualiza o livro de ordens 
            if ($ladoOposto === 'sell') {
                OrderBook::atualizaOrdemVenda($ordem);
            } else {
                OrderBook::atualizaOrdemCompra($ordem);
            }
        }

        // Exibe o resultado do trade no console
        if ($qtySelecionada > 0) {
            $this->info("Trade, price: {$price}, qty: {$qtySelecionada}");
        }

        // Se não houve trade realizado, registrar a ordem no livro 
        if ($qtySelecionada === 0 && $qty > 0) {
            if ($ladoOposto === 'sell') {
                OrderBook::adicionarOrdemCompra($price, $qty);
            } else {
                OrderBook::adicionarOrdemVenda($price, $qty);
            }
        }
    }

    public function executandoLimitOrder($ladoOposto, $precoLimite, $qty)
    {
        // Obter as ordens do lado oposto
        if ($ladoOposto === 'sell') {
            $ordem = OrderBook::ordemVenda();
        } else {
            $ordem = OrderBook::ordemCompra();
        }

        // se não houver ordens do lado oposto, não é possível realizar o trade
        if (empty($ordem)) {
            $this->info("Não há ordens do lado oposto para realizar o trade.");
            return;
        }

        $qtySelecionada = 0; // Quantidade preenchida no trade
        $tradePreco = 0; // Preço que o trade foi executado

        // Verifica as ordens opostas
        foreach ($ordem as $index => $order) {
            // Verifica se o preço da ordem oposta é compatível com o limite
            if (($ladoOposto === 'sell' && $order['price'] <= $precoLimite) ||
                ($ladoOposto === 'buy' && $order['price'] >= $precoLimite)
            ) {

                if ($order['qty'] >= $qty) {
                    // Trade completo
                    $qtySelecionada = $qty;
                    $tradePreco = $order['price'];
                    $ordem[$index]['qty'] -= $qty;

                    // Remove a ordem se a quantidade for 0
                    if ($ordem[$index]['qty'] == 0) {
                        unset($ordem[$index]);
                    }
                    break;
                } else {
                    // Trade parcial
                    $qtySelecionada += $order['qty'];
                    $tradePreco = $order['price'];
                    $qty -= $order['qty'];
                    unset($ordem[$index]);
                }
            }
        }

        // Atualiza o livro de ordens
        if ($ladoOposto === 'sell') {
            OrderBook::atualizaOrdemVenda($ordem);
        } else {
            OrderBook::atualizaOrdemCompra($ordem);
        }

        // Mostra o resultado do trade
        if ($qtySelecionada > 0) {
            $this->newLine();
            $this->info("Trade, price: {$tradePreco}, qty: {$qtySelecionada}");
        } else {
            $this->info("Não foi possível realizar o trade.");
        }
    }
}
