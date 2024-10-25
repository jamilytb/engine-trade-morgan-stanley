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
            $this->info("1. Inserir limit order");
            $this->info("2. Inserir market order");
            $this->info("3. Exibir histórico de ordens de compra");
            $this->info("4. Exibir histórico de ordens de venda");
            $this->info("5. Cancelar ordem");
            $this->info("6. Alterar ordem");
            $this->info("7. Sair");

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
                    $id = $this->ask("Informe o ID da ordem que deseja cancelar");
                    $this->cancelarOrdem($id);
                    break;
                case 6:
                    $id = $this->ask("Informe o ID da ordem que deseja alterar");
                    $this->alterarOrdem($id);
                    break;
                case 7:
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
        $this->info("Limit order inserida com sucesso!");
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
        $this->info("Market order processada com sucesso!");
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

            // Remove a quantidade da ordem de venda
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

    public function exibirOrdensCompra()
    {
        // Ordena as ordens de compra pelo preço em ordem decrescente
        $ordensCompraOrdenadas = $this->buyOrders->sortByDesc('preco');

        $this->info("===== Ordens de Compra =====");

        foreach ($ordensCompraOrdenadas as $ordem) {
            $this->info(" - Quantidade: {$ordem->quantidade}, Preço: {$ordem->preco}");
        }

        $this->info("=====================");
    }

    public function exibirOrdensVenda()
    {
        // Ordena as ordens de venda pelo preço em ordem crescente
        $ordensVendaOrdenadas = $this->sellOrders->sortBy('preco');

        $this->info("===== Ordens de Venda =====");

        foreach ($ordensVendaOrdenadas as $ordem) {
            $this->info(" - Quantidade: {$ordem->quantidade}, Preço: {$ordem->preco}");
        }

        $this->info("=====================");
    }

    public function exibirHistorico()
    {
        // Ordena as ordens pelo preço em ordem decrescente
        $historicoOrdenado = $this->minhasCompras->sortByDesc('preco');

        $this->info("===== Histórico =====");

        foreach ($historicoOrdenado as $ordem) {
            $this->info(" {$ordem['id']} {$ordem['tipo']} {$ordem['lado']} {$ordem['quantidade']} @ {$ordem['preco']}");
        }

        $this->info("=====================");
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

    protected function atualizarHistorico(string $id, $ordemAlteravel): void
    {
        // Verifica se a ordem existe no histórico
        $ordemExistente = $this->minhasCompras->firstWhere('id', $id);

        if ($ordemExistente) {
            // Atualiza os dados da ordem
            $this->minhasCompras = $this->minhasCompras->transform(function ($ordem) use ($ordemExistente, $ordemAlteravel) {
                if ($ordem['id'] === $ordemExistente['id']) {
                    // Retorna a ordem atualizada
                    return [
                        'id' => $ordem['id'],
                        'tipo' => $ordem['tipo'],
                        'lado' => $ordem['lado'],
                        'preco' => $ordemAlteravel->preco, // Novo preço
                        'quantidade' => $ordemAlteravel->quantidade, // Nova quantidade
                    ];
                }
                return $ordem; // Retorna a ordem original se não for a que está sendo alterada
            });

            $this->info("Ordem com ID {$id} atualizada com sucesso.");
        } else {
            $this->error("Ordem com ID {$id} não encontrada no histórico.");
        }
    }

    // Implementa o método de cancelamento
    protected function cancelarOrdem(string $id): void
    {
        // Verifica se a ordem é do tipo limit e não está no histórico de ordens executadas (minhasCompras)
        $ordemCancelavel = $this->buyOrders->firstWhere('id', $id) ?? $this->sellOrders->firstWhere('id', $id);

        if ($ordemCancelavel && $ordemCancelavel->tipo === 'limit' && !$this->minhasCompras->contains('id', $id)) {
            // Remove a ordem da lista de ordens de compra
            $this->buyOrders = $this->buyOrders->reject(function ($order) use ($id) {
                return $order->id === $id;
            });

            // Remove a ordem da lista de ordens de venda
            $this->sellOrders = $this->sellOrders->reject(function ($order) use ($id) {
                return $order->id === $id;
            });

            $this->info("Ordem com ID {$id} cancelada com sucesso.");
        } else {
            $this->error("A ordem com ID {$id} não pode ser cancelada. Somente ordens limit não executadas são canceláveis.");
        }
    }

    // Implementa o método de alteração de ordem
    protected function alterarOrdem(string $id): void
    {
        // Verifica se a ordem existe no histórico
        $ordemAlteravel = $this->minhasCompras->firstWhere('id', $id);

        // Certifique-se de que a ordem foi encontrada
        if ($ordemAlteravel) {
            // Converte a ordem para um objeto, se necessário
            if (is_array($ordemAlteravel)) {
                $ordemAlteravel = (object) $ordemAlteravel;
            }

            // Verifica se é do tipo limit
            if ($ordemAlteravel->tipo === 'limit') {
                // Exibe a ordem encontrada
                $this->info("Ordem encontrada: ID {$id}, Tipo: {$ordemAlteravel->tipo}, Lado: {$ordemAlteravel->lado}, Preço: {$ordemAlteravel->preco}, Quantidade: {$ordemAlteravel->quantidade}");

                // Pergunta ao usuário o que deseja alterar
                $campo = $this->ask("Deseja alterar 'preço' ou 'quantidade'?");

                switch ($campo) {
                    case 'preço':
                        $novoPreco = $this->ask("Informe o novo preço");
                        $ordemAlteravel->preco = (float)$novoPreco;
                        $this->info("Preço da ordem {$id} alterado para {$novoPreco}.");
                        break;

                    case 'quantidade':
                        $novaQuantidade = $this->ask("Informe a nova quantidade");
                        $ordemAlteravel->quantidade = (int)$novaQuantidade;
                        $this->info("Quantidade da ordem {$id} alterada para {$novaQuantidade}.");
                        break;

                    default:
                        $this->error("Opção inválida. Informe 'preço' ou 'quantidade'.");
                        return;
                }

                // Atualiza a ordem no histórico após a alteração
                $this->atualizarHistorico($id, $ordemAlteravel);
            } else {
                $this->error("A ordem com ID {$id} não pode ser alterada. Somente ordens limit não executadas são alteráveis.");
            }
        } else {
            $this->error("A ordem com ID {$id} não foi encontrada.");
        }
    }
}
