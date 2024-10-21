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
            $this->info("1. Inserir ordem limit");
            $this->info("2. Inserir ordem market");
            $this->info("3. Exibir ordens de compra");
            $this->info("4. Exibir ordens de venda");
            $this->info("5. Exibir livro de ordens (TXT)");
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
                case 5:
                    $this->processarArquivosTxt();
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
        $ordensCompra = $this->lerArquivoOrdens($caminhoCompras);
        $ordensVenda = $this->lerArquivoOrdens($caminhoVendas);

        // Chama o método que exibe o livro de ordens
        $this->exibirLivroDeOrdens($ordensCompra, $ordensVenda);
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
        $qty = $this->ask('Digite a quantidade'); // Solicita a quantidade
        $preco = $this->ask('Digite o preço'); // Pergunta o preço

        if ($side === 'buy') {
            $novaOrdem = [
                'id' => $this->proximoIdCompra++, // Incrementa o próximo ID de compra
                'quantidade' => (int)$qty,
                'preco' => (float)$preco,
            ];

            // Adiciona a nova ordem ao arquivo
            $caminhoCompras = storage_path('app/compras.txt');
            $ordensCompra = $this->lerArquivoOrdens($caminhoCompras);
            $ordensCompra[] = $novaOrdem; // Adiciona a nova ordem

            // Reescreve as ordens no arquivo
            $conteudo = '';
            foreach ($ordensCompra as $ordem) {
                $conteudo .= "{$ordem['id']} - {$ordem['quantidade']} @ {$ordem['preco']}\n";
            }

            file_put_contents($caminhoCompras, $conteudo);
            $this->info("Ordem de mercado de compra adicionada: {$novaOrdem['quantidade']} @ {$novaOrdem['preco']}");
        } elseif ($side === 'sell') {
            $novaOrdem = [
                'id' => $this->proximoIdVenda++, // Incrementa o próximo ID de venda
                'quantidade' => (int)$qty,
                'preco' => (float)$preco,
            ];

            // Adiciona a nova ordem ao arquivo
            $caminhoVendas = storage_path('app/vendas.txt');
            $ordensVenda = $this->lerArquivoOrdens($caminhoVendas);
            $ordensVenda[] = $novaOrdem; // Adiciona a nova ordem

            // Reescreve as ordens no arquivo
            $conteudo = '';
            foreach ($ordensVenda as $ordem) {
                $conteudo .= "{$ordem['id']} - {$ordem['quantidade']} @ {$ordem['preco']}\n";
            }

            file_put_contents($caminhoVendas, $conteudo);
            $this->info("Ordem de mercado de venda adicionada: {$novaOrdem['quantidade']} @ {$novaOrdem['preco']}");
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
