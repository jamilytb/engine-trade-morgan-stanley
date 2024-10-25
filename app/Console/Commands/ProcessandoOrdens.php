<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Ordem;

class ProcessandoOrdens extends Command
{
    protected $signature = 'X';
    protected $description = 'Processa ordens de compra ou venda';

    protected $ordemCompra;
    protected $ordemVenda;
    protected $precoAtivo = null;
    protected $proximoIdCompra = 1; // Inicia em 1 para ordens de compra
    protected $proximoIdVenda = 1;   // Inicia em 1 para ordens de venda
    protected $carteira = []; // Armazena as ações compradas
    protected $ordensVenda = [];
    protected $ordensCompra = [];
    
    // Novos arrays para armazenar as ordens limit
    protected $ordensLimitCompra = [];
    protected $ordensLimitVenda = [];

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
            // Armazena a ordem limit de compra
            $this->ordensLimitCompra[] = [
                'id' => $this->proximoIdCompra,
                'preco' => $price,
                'quantidade' => $qty,
            ];
            // Adiciona também no array de ordens de compra
            $this->ordensCompra[] = [
                'id' => $this->proximoIdCompra,
                'preco' => $price,
                'quantidade' => $qty,
            ];
            $this->info("Ordem limit de compra adicionada. ID: {$this->proximoIdCompra}");
            $this->proximoIdCompra++; // Incrementa o próximo ID de compra
        } elseif ($side === 'sell') {
            // Armazena a ordem limit de venda
            $this->ordensLimitVenda[] = [
                'id' => $this->proximoIdVenda,
                'precFo' => $price,
                'quantidade' => $qty,
            ];
            $this->info("Ordem limit de venda adicionada. ID: {$this->proximoIdVenda}");
            $this->proximoIdVenda++; // Incrementa o próximo ID de venda
        }
    }
    
    // Método para inserir uma ordem de mercado
    public function inserirMarketOrder()
{
    $side = $this->choice('Digite o lado (buy/sell)', ['buy', 'sell']);
    $qtyDesejada = (int)$this->ask('Digite a quantidade');

    if ($side === 'buy') {
        // Ordenar as ordens de venda pelo preço mais baixo
        usort($this->ordensVenda, function ($a, $b) {
            return $a['preco'] <=> $b['preco'];
        });

        $quantidadeRestante = $qtyDesejada;
        $totalComprado = 0;
        $precoMedio = 0;

        foreach ($this->ordensVenda as $index => &$ordemVenda) {
            if ($quantidadeRestante <= 0) {
                break;
            }

            if ($ordemVenda['quantidade'] <= $quantidadeRestante) {
                // Compra total da ordem de venda
                $this->info("Comprado {$ordemVenda['quantidade']} ações a {$ordemVenda['preco']}");
                $totalComprado += $ordemVenda['quantidade'];
                $precoMedio = $ordemVenda['preco'];
                $quantidadeRestante -= $ordemVenda['quantidade'];
                unset($this->ordensVenda[$index]); // Remove a ordem de venda completamente
            } else {
                // Compra parcial da ordem de venda
                $this->info("Comprado {$quantidadeRestante} ações a {$ordemVenda['preco']}");
                $totalComprado += $quantidadeRestante;
                $precoMedio = $ordemVenda['preco'];
                $ordemVenda['quantidade'] -= $quantidadeRestante; // Subtrai a quantidade comprada
                $quantidadeRestante = 0; // Não há mais quantidade restante
            }
        }

        // Atualiza a carteira em memória
        if ($totalComprado > 0) {
            // Verifica se o ativo já está na carteira
            if (isset($this->carteira['AAPL'])) {
                // Se já existir, atualiza a quantidade
                $this->carteira['AAPL']['quantidade'] += $totalComprado;
                // Atualiza o preço com o último preço de compra
                $this->carteira['AAPL']['preco'] = $precoMedio;
            } else {
                // Se não existir, adiciona o ativo à carteira
                $this->carteira['AAPL'] = [
                    'quantidade' => $totalComprado,
                    'preco' => $precoMedio
                ];
            }

            // Armazena a ordem market buy apenas se alguma ação foi comprada
            $this->ordensCompra[] = [
                'id' => $this->proximoIdCompra,
                'quantidade' => $totalComprado,
                'preco' => 'mercado',
            ];
            $this->proximoIdCompra++;
        } else {
            $this->info("Nenhuma ação foi comprada.");
        }
    } elseif ($side === 'sell') {
        // Lógica de venda
    }
}
    
// Método para mostrar as ordens de compra
public function mostrarOrdensCompra()
{
    $this->info("\n====================  ORDENS DE COMPRA (CARTEIRA)  =====================\n");

    // Verifica se há ações compradas na carteira
    if (empty($this->carteira)) {
        $this->info("Nenhuma ação comprada encontrada.");
        return;
    }

    // Exibe as ações armazenadas na carteira com as novas colunas
    $this->info(sprintf("%-10s %-10s %-10s", "Ativo", "QTY", "Price"));
    $this->info(str_repeat("=", 40));

    foreach ($this->carteira as $ticker => $detalhes) {
        $this->info(sprintf("%-10s %-10s %-10s", $ticker, $detalhes['quantidade'], $detalhes['preco']));
    }

    // Verifica se há ordens de compra pendentes
    if (empty($this->ordensCompra)) {
        $this->info("\nNenhuma ordem de compra pendente.");
        return;
    }
}

    // Método para mostrar ordens de venda
    public function mostrarOrdensVenda()
    {
        $this->info("\n====================  ORDENS DE VENDA  =====================\n");
        if (empty($this->ordensLimitVenda) && empty($this->ordensVenda)) {
            $this->info("Nenhuma ordem de venda encontrada.");
            return;
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
