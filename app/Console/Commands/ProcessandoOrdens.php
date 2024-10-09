<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\OrderBook;

class ProcessandoOrdens extends Command
{
    protected $signature = 'order:process';
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
                    $this->insertLimitOrder();
                    break;
                case 2:
                    $this->insertMarketOrder();
                    break;
                case 3:
                    $this->showBuyOrders();
                    break;
                case 4:
                    $this->showSellOrders();
                    break;
                case 5:
                    $this->info('Saindo...');
                    return;
                default:
                    $this->error('Opção inválida! Tente novamente.');
            }
        }
    }

    public function insertLimitOrder()
    {
        $side = $this->ask('Digite o lado (buy/sell)');
        $price = $this->ask('Digite o preço');  // Preço limite que o usuário define
        $qty = $this->ask('Digite a quantidade');

        if ($side === 'buy') {
            // Adiciona ordem de compra ao livro
            OrderBook::addBuyOrder($price, $qty);
            $this->info('Order limit de compra adicionada com sucesso.');

            // Tenta executar a ordem com base nas ordens de venda existentes
            $this->executeLimitOrder('sell', $price, $qty);
        } elseif ($side === 'sell') {
            // Adiciona ordem de venda ao livro
            OrderBook::addSellOrder($price, $qty);
            $this->info('Order limit de venda adicionada com sucesso.');

            // Tenta executar a ordem com base nas ordens de compra existentes
            $this->executeLimitOrder('buy', $price, $qty);
        } else {
            $this->error('Lado inválido! Use "buy" ou "sell".');
        }
    }

    public function insertMarketOrder()
    {
        $side = $this->ask('Digite o lado (buy/sell)');
        $qty = $this->ask('Digite a quantidade');
        $marketPrice = OrderBook::getAssetPrice(); // Preço de mercado fixo (50.00)

        if ($side === 'buy') {
            // Tenta executar a ordem de compra usando ordens de venda
            $filledQty = $this->executeTrade('sell', $qty);

            // Se não houver ordens do lado oposto, adiciona a ordem ao livro
            if ($filledQty === 0) {
                OrderBook::addBuyOrder($marketPrice, $qty);
            }
        } elseif ($side === 'sell') {
            // Tenta executar a ordem de venda usando ordens de compra
            $filledQty = $this->executeTrade('buy', $qty);

            // Se não houver ordens do lado oposto, adiciona a ordem ao livro
            if ($filledQty === 0) {
                OrderBook::addSellOrder($marketPrice, $qty);
            }
        } else {
            $this->error('Lado inválido! Use "buy" ou "sell".');
            return;
        }

        // Exibe o trade com o preço de mercado
        $this->info("Trade, price: {$marketPrice}, qty: {$qty}");
    }

    public function showBuyOrders()
    {
        $buyOrders = OrderBook::getBuyOrders();
        if (empty($buyOrders)) {
            $this->info('Não há ordens de compra.');
        } else {
            $this->info("Ordens de compra:");
            foreach ($buyOrders as $order) {
                $this->info("Price: {$order['price']}, Qty: {$order['qty']}");
            }
        }
    }

    public function showSellOrders()
    {
        $sellOrders = OrderBook::getSellOrders();
        if (empty($sellOrders)) {
            $this->info('Não há ordens de venda.');
        } else {
            $this->info("Ordens de venda:");
            foreach ($sellOrders as $order) {
                $this->info("Price: {$order['price']}, Qty: {$order['qty']}");
            }
        }
    }

    public function executeTrade($oppositeSide, $qty)
    {
        $price = OrderBook::getAssetPrice(); // Preço fixo do ativo (50.00)
        $filledQty = 0; // Quantidade preenchida no trade

        // Se a ordem for de compra
        if ($oppositeSide === 'sell') {
            $orders = OrderBook::getSellOrders(); // Ordens de venda
        } else {
            $orders = OrderBook::getBuyOrders(); // Ordens de compra
        }

        // Tentar preencher as ordens se existirem
        if (!empty($orders)) {
            // Iterar pelas ordens até preencher a quantidade necessária
            foreach ($orders as $index => $order) {
                if ($order['qty'] >= $qty) {
                    // Trade completo
                    $filledQty = $qty;
                    $order['qty'] -= $qty;

                    // Remover ordem se a quantidade for 0
                    if ($order['qty'] == 0) {
                        unset($orders[$index]);
                    }
                    break; // Saímos do loop após preencher a ordem
                } else {
                    // Trade parcial
                    $filledQty += $order['qty'];
                    $qty -= $order['qty'];
                    unset($orders[$index]); // Remover ordem já completamente utilizada
                }
            }

            // Atualizar o livro de ordens com as ordens restantes
            if ($oppositeSide === 'sell') {
                OrderBook::updateSellOrders($orders);
            } else {
                OrderBook::updateBuyOrders($orders);
            }
        }

        // Exibir resultado do trade uma única vez
        if ($filledQty > 0) {
            $this->info("Trade, price: {$price}, qty: {$filledQty}");
        }

        // Se não houve trade realizado, registrar a ordem
        if ($filledQty === 0 && $qty > 0) {
            if ($oppositeSide === 'sell') {
                OrderBook::addBuyOrder($price, $qty);
            } else {
                OrderBook::addSellOrder($price, $qty);
            }
        }
    }

    public function executeLimitOrder($oppositeSide, $limitPrice, $qty)
    {
        // Obter as ordens do lado oposto
        $orders = $oppositeSide === 'sell' ? OrderBook::getSellOrders() : OrderBook::getBuyOrders();

        // Verificar se existem ordens do lado oposto
        if (empty($orders)) {
            $this->info("Não há ordens do lado oposto para realizar o trade.");
            return;
        }

        $filledQty = 0; // Quantidade preenchida no trade
        $tradePrice = 0; // Preço no qual o trade foi executado

        // Percorre as ordens opostas
        foreach ($orders as $index => $order) {
            // Verifica se o preço da ordem oposta é compatível com o limite
            if (($oppositeSide === 'sell' && $order['price'] <= $limitPrice) ||
                ($oppositeSide === 'buy' && $order['price'] >= $limitPrice)
            ) {

                if ($order['qty'] >= $qty) {
                    // Trade completo
                    $filledQty = $qty;
                    $tradePrice = $order['price'];
                    $orders[$index]['qty'] -= $qty;

                    // Remove a ordem se a quantidade for 0
                    if ($orders[$index]['qty'] == 0) {
                        unset($orders[$index]);
                    }
                    break; // Saímos do loop após preencher a ordem
                } else {
                    // Trade parcial
                    $filledQty += $order['qty'];
                    $tradePrice = $order['price'];
                    $qty -= $order['qty'];
                    unset($orders[$index]); // Remover ordem já completamente utilizada
                }
            }
        }

        // Atualiza o livro de ordens
        if ($oppositeSide === 'sell') {
            OrderBook::updateSellOrders($orders);
        } else {
            OrderBook::updateBuyOrders($orders);
        }

        // Mostra o resultado do trade
        if ($filledQty > 0) {
            $this->newLine();
            $this->info("Trade, price: {$tradePrice}, qty: {$filledQty}");
        } else {
            $this->info("Não foi possível realizar o trade no preço limite.");
        }
    }
}
