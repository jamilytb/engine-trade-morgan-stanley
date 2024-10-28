<?php

namespace App\Models;

use Illuminate\Support\Collection;

class OrderService
{
    protected Collection $buyOrders;
    protected Collection $sellOrders;
    protected Collection $historico;

    public function __construct()
    {
        $this->buyOrders = collect();
        $this->sellOrders = collect();
        $this->historico = collect();
    }

    public function adicionarOrdem(Order $ordem): void
    {
        if ($ordem->lado === 'buy') {
            $this->buyOrders->push($ordem);
        } else {
            $this->sellOrders->push($ordem);
        }
    }

    // Método para processar ordens de compra
    public function processarOrdemCompra(Order $ordem): void
    {
        while ($ordem->quantidade > 0) {
            // Pesquisa o menor preço na coleção de ordens de venda
            $sellOrder = $this->sellOrders->sortBy('preco')->first();

            // Se não houver mais ordens de venda, interrompe o processo
            if (!$sellOrder) {
                break;
            }

            // Em ordens limit, verifica se o preço da ordem de compra é menor que o preço da venda
            if ($ordem->tipo === 'limit' && $ordem->preco < $sellOrder->preco) {
                $this->guardarHistorico(
                    id: $ordem->id,
                    lado: $ordem->lado,
                    preco: $ordem->preco,
                    quantidade: $ordem->quantidade,
                    tipo: $ordem->tipo,
                    trade: false
                );
                break;
            }

            // Remove a quantidade da ordem de venda
            $quantidadePreenchida = min($ordem->quantidade, $sellOrder->quantidade);
            $ordem->quantidade -= $quantidadePreenchida;
            $sellOrder->quantidade -= $quantidadePreenchida;

            // Exibe o trade realizado
            $this->guardarHistorico(
                id: $ordem->id,
                lado: $ordem->lado,
                preco: $sellOrder->preco,
                quantidade: $quantidadePreenchida,
                tipo: $ordem->tipo,
                trade: true
            );
            $this->exibirTrade($sellOrder->preco, $quantidadePreenchida);

            // Se a quantidade da ordem de venda for 0, remove a ordem de venda
            if ($sellOrder->quantidade === 0) {
                $this->sellOrders = $this->sellOrders->reject(fn($value) => $value->id === $sellOrder->id);
            }
        }

        // Ordena as ordens de compra e venda
        $this->ordenarOrdens();
    }

    public function processarOrdemVenda(Order $ordem): void
    {
        while ($ordem->quantidade > 0) {
            // Pesquisa o maior preço na coleção de ordens de compra
            $buyOrder = $this->buyOrders->sortByDesc('preco')->first();

            // Se não houver mais ordens de compra, interrompe o processo
            if (!$buyOrder) {
                break;
            }

            // Em ordens limit, verifica se o preço da ordem de venda é maior que o preço da compra
            if ($ordem->tipo === 'limit' && $ordem->preco > $buyOrder->preco) {
                $this->guardarHistorico(
                    id: $ordem->id,
                    lado: $ordem->lado,
                    preco: $ordem->preco,
                    quantidade: $ordem->quantidade,
                    tipo: $ordem->tipo,
                    trade: false
                );
                break;
            }

            // Remove a quantidade da ordem de compra
            $quantidadePreenchida = min($ordem->quantidade, $buyOrder->quantidade);
            $ordem->quantidade -= $quantidadePreenchida;
            $buyOrder->quantidade -= $quantidadePreenchida;

            // Exibe o trade realizado
            $this->guardarHistorico(
                id: $ordem->id,
                lado: $ordem->lado,
                preco: $buyOrder->preco,
                quantidade: $quantidadePreenchida,
                tipo: $ordem->tipo,
                trade: true
            );

            $this->exibirTrade($buyOrder->preco, $quantidadePreenchida);

            // Se a quantidade da ordem de compra for 0, remove a ordem de compra
            if ($buyOrder->quantidade === 0) {
                $this->buyOrders = $this->buyOrders->reject(fn($value) => $value->id === $buyOrder->id);
            }
        }

        // Ordena as ordens de compra e venda
        $this->ordenarOrdens();
    }

    // Método para processar ordens de mercado
    public function processarOrdemMarket(Order $ordem): void
    {
        if ($ordem->lado === 'buy') {
            $this->processarOrdemCompra($ordem);
        } else {
            $this->processarOrdemVenda($ordem);
        }
    }

    public function cancelarOrdem(string $id): bool
    {
        // Busca a ordem no histórico e verifica se pode ser cancelada
        $ordemCancelavel = $this->historico->where('id', $id)->where('trade', false)->first();

        if (!$ordemCancelavel) {
            return false;
        }

        if ($ordemCancelavel['tipo'] === 'limit') {
            // Remove a ordem do histórico
            $this->historico = $this->historico->reject(function ($value) use ($ordemCancelavel) {
                return $value['id'] === $ordemCancelavel['id'];
            });
            return true;
        }

        return false;
    }

    public function alterarOrdem(string $id, string $campo, $novoValor): bool
    {
        // Busca a ordem no histórico para edição
        $ordemAlteravel = $this->historico->firstWhere('id', $id);

        if ($ordemAlteravel && $ordemAlteravel['tipo'] === 'limit') {
            // Atualiza o campo específico da ordem
            $this->historico = $this->historico->map(function ($ordem) use ($id, $campo, $novoValor) {
                if ($ordem['id'] === $id) {
                    $ordem[$campo] = $campo === 'preco' ? (float)$novoValor : (int)$novoValor;
                }
                return $ordem;
            });
            return true;
        }

        return false;
    }

    public function OrdemId(string $id): ?array
    {
        return $this->historico->firstWhere('id', $id);
    }

    protected function guardarHistorico(string $id, string $lado, float $preco, int $quantidade, string $tipo, bool $trade): void
    {
        $this->historico->push([
            'id' => $id,
            'tipo' => $tipo,
            'lado' => $lado,
            'quantidade' => $quantidade,
            'preco' => $preco,
            'trade' => $trade,
        ]);
    }

    protected function exibirTrade(float $preco, int $quantidade): void
    {
        echo "Trade, price: {$preco}, qty: {$quantidade}\n";
    }

    protected function ordenarOrdens(): void
    {
        $this->buyOrders = $this->buyOrders->sortByDesc('preco')->values();
        $this->sellOrders = $this->sellOrders->sortBy('preco')->values();
    }

    protected function getMenorPrecoOrdemVenda(): Order
    {
        return $this->sellOrders->sortBy('preco')->first();
    }

    // Método para obter o histórico de ordens ordenado
    public function HistoricoOrdenado(): Collection
    {
        return $this->historico->sortByDesc('preco')->values();
    }

    public function OrdensCompra(): Collection
    {
        return $this->buyOrders;
    }

    public function OrdensVenda(): Collection
    {
        return $this->sellOrders;
    }
}
