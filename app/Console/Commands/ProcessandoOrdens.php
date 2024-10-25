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
    protected $precoAtivo = null;
    protected $proximoIdCompra = 1; // Inicia em 1 para ordens de compra
    protected $proximoIdVenda = 1;   // Inicia em 1 para ordens de venda
    protected $carteira = []; // Armazena as ações compradas
    protected $ordensVenda = [];
    protected $ordensCompra = [];

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
            // Processa e exibe o livro de ordens como cabeçalho
            $this->info("\n====================  LIVRO DE ORDENS  =====================\n");
            $this->processarArquivosTxt();

            // Exibe o menu de opções
            $this->info("\n====================  MENU  ====================\n");
            $this->info("1. Inserir ordem limit");
            $this->info("2. Inserir ordem market");
            $this->info("3. Exibir ordens de compra");
            $this->info("4. Exibir ordens de venda");
            $this->info("6. Sair");

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
                case 6:
                    $this->info('Saindo...');
                    return;
                default:
                    $this->error('Opção inválida! Tente novamente.');
            }
        }
    }

    // Função para ler os arquivos .txt com simulações de compra e venda
    public function processarArquivosTxt()
    {
        // Caminho dos arquivos de compra e venda
        $caminhoCompras = storage_path('app/compras.txt');
        $caminhoVendas = storage_path('app/vendas.txt');

        // Lê os arquivos de compra e venda
        $this->ordensCompra = $this->lerArquivoOrdens($caminhoCompras);
        $this->ordensVenda = $this->lerArquivoOrdens($caminhoVendas);

        // Chama o método que exibe o livro de ordens
        $this->exibirLivroDeOrdens($this->ordensCompra, $this->ordensVenda);
    }

    public function lerArquivoOrdens($caminhoArquivo)
    {
        $ordens = [];
        if (file_exists($caminhoArquivo)) {
            $linhas = file($caminhoArquivo, FILE_IGNORE_NEW_LINES);

            foreach ($linhas as $index => $linha) {
                $dados = explode(' @ ', trim($linha)); // Corrige para separar por ' @ '
                if (count($dados) !== 2) {
                    $this->error("Formato inválido na linha: $linha");
                    continue; // Ignora linhas mal formatadas
                }

                $quantidade = (int)$dados[0]; // Quantidade
                $preco = (float)$dados[1]; // Preço
                $ordens[] = ['id' => $index + 1, 'quantidade' => $quantidade, 'preco' => $preco]; // Adiciona ID
            }
        }

        return $ordens;
    }

    public function exibirLivroDeOrdens($ordensCompra, $ordensVenda)
    {
        $this->info(sprintf("%-10s %-20s %-20s", "ID", "Buy", "Sell"));
        $this->info(str_repeat("=", 60));

        for ($i = 0; $i < max(count($ordensCompra), count($ordensVenda)); $i++) {
            $compra = isset($ordensCompra[$i]) ? "{$ordensCompra[$i]['quantidade']} @ {$ordensCompra[$i]['preco']}" : '';
            $venda = isset($ordensVenda[$i]) ? "{$ordensVenda[$i]['quantidade']} @ {$ordensVenda[$i]['preco']}" : '';

            $this->info(sprintf("%-10s %-20s %-20s", ($i + 1), $compra, $venda));
        }
    }

    // Método para inserir uma ordem limit
    public function inserirLimitOrder()
    {
        $side = $this->choice('Digite o lado (buy/sell)', ['buy', 'sell']);
        $price = $this->ask('Digite o preço');
        $qty = $this->ask('Digite a quantidade');

        if ($side === 'buy') {
            $this->ordemCompra->atualizar($price, $qty, $this->proximoIdCompra);
            $this->proximoIdCompra++; // Incrementa o próximo ID de compra
            $this->info("Ordem limit de compra adicionada. ID: {$this->ordemCompra->id}");
        } elseif ($side === 'sell') {
            $this->ordemVenda->atualizar($price, $qty, $this->proximoIdVenda);
            $this->proximoIdVenda++; // Incrementa o próximo ID de venda
            $this->info("Ordem limit de venda adicionada. ID: {$this->ordemVenda->id}");
        }
    }

    // Método para inserir uma ordem de mercado e executar o trade imediatamente
    public function inserirMarketOrder()
    {
        $side = $this->choice('Digite o lado (buy/sell)', ['buy', 'sell']);
        $qtyDesejada = (int)$this->ask('Digite a quantidade'); // Solicita a quantidade

        // As ordens de compra e venda já estão carregadas em memória
        $ordensCompra = $this->ordensCompra;
        $ordensVenda = $this->ordensVenda;

        if ($side === 'buy') {
            // Ordenar as ordens de venda pelo preço mais baixo
            usort($ordensVenda, function ($a, $b) {
                return $a['preco'] <=> $b['preco'];
            });

            $quantidadeRestante = $qtyDesejada;
            $totalComprado = 0;

            foreach ($ordensVenda as $index => &$ordemVenda) {
                if ($quantidadeRestante <= 0) {
                    break;
                }

                if ($ordemVenda['quantidade'] <= $quantidadeRestante) {
                    // Executa o trade para a quantidade total da ordem de venda
                    $this->info("Comprado {$ordemVenda['quantidade']} ações a {$ordemVenda['preco']}");
                    $totalComprado += $ordemVenda['quantidade'];
                    $quantidadeRestante -= $ordemVenda['quantidade'];
                    // Remove a ordem de venda do array
                    unset($ordensVenda[$index]);
                } else {
                    // Executa o trade para a quantidade parcial
                    $this->info("Comprado {$quantidadeRestante} ações a {$ordemVenda['preco']}");
                    $totalComprado += $quantidadeRestante;
                    $ordemVenda['quantidade'] -= $quantidadeRestante;
                    $quantidadeRestante = 0;
                }
            }

            if ($quantidadeRestante > 0) {
                $this->info("Ordem de compra não totalmente preenchida. Restam {$quantidadeRestante} ações a serem compradas.");
            }

            // Atualiza a carteira em memória
            if (isset($this->carteira['AAPL'])) {
                $this->carteira['AAPL'] += $totalComprado;
            } else {
                $this->carteira['AAPL'] = $totalComprado;
            }
        } elseif ($side === 'sell') {
            // Verifica se tem ações suficientes para vender
            if (!isset($this->carteira['AAPL']) || $this->carteira['AAPL'] < $qtyDesejada) {
                $this->error("Quantidade insuficiente para vender.");
                return;
            }

            $quantidadeRestante = $qtyDesejada;
            $totalVendido = 0;

            // Ordenar as ordens de compra pelo preço mais alto
            usort($ordensCompra, function ($a, $b) {
                return $b['preco'] <=> $a['preco'];
            });

            foreach ($ordensCompra as $index => &$ordemCompra) {
                if ($quantidadeRestante <= 0) {
                    break;
                }

                if ($ordemCompra['quantidade'] <= $quantidadeRestante) {
                    // Executa o trade para a quantidade total da ordem de compra
                    $this->info("Vendido {$ordemCompra['quantidade']} ações a {$ordemCompra['preco']}");
                    $totalVendido += $ordemCompra['quantidade'];
                    $quantidadeRestante -= $ordemCompra['quantidade'];
                    // Remove a ordem de compra do array
                    unset($ordensCompra[$index]);
                } else {
                    // Executa o trade para a quantidade parcial
                    $this->info("Vendido {$quantidadeRestante} ações a {$ordemCompra['preco']}");
                    $totalVendido += $quantidadeRestante;
                    $ordemCompra['quantidade'] -= $quantidadeRestante;
                    $quantidadeRestante = 0;
                }
            }

            // Atualiza a carteira em memória
            $this->carteira['AAPL'] -= $totalVendido;

            if ($quantidadeRestante > 0) {
                $this->info("Ordem de venda não totalmente preenchida. Restam {$quantidadeRestante} ações a serem vendidas.");
            }
        }

        // Após cada operação, atualiza os arrays de ordens de compra e venda
        $this->ordensCompra = $ordensCompra;
        $this->ordensVenda = $ordensVenda;
    }

    public function mostrarOrdensCompra()
    {
        $this->info("Ordens de compra:");
        foreach ($this->ordensCompra as $ordem) {
            $this->info("ID: {$ordem['id']}, Quantidade: {$ordem['quantidade']}, Preço: {$ordem['preco']}");
        }
    }

    public function mostrarOrdensVenda()
    {
        $this->info("Ordens de venda:");
        foreach ($this->ordensVenda as $ordem) {
            $this->info("ID: {$ordem['id']}, Quantidade: {$ordem['quantidade']}, Preço: {$ordem['preco']}");
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
