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
        $qty = (int)$this->ask('Digite a quantidade'); // Solicita apenas a quantidade

        if ($side === 'buy') {
            // Lê as ordens de venda do arquivo vendas.txt
            $caminhoVendas = storage_path('app/vendas.txt');
            $ordensVenda = $this->lerArquivoOrdens($caminhoVendas);

            if (empty($ordensVenda)) {
                $this->info('Não há ordens de venda disponíveis.');
                return;
            }

            $quantidadeRestante = $qty; // Quantidade que ainda precisa ser comprada

            // Processa a compra a partir das vendas no menor preço
            foreach ($ordensVenda as $index => $venda) {
                if ($quantidadeRestante > 0) {
                    $quantidadeVenda = $venda['quantidade'];
                    $precoVenda = $venda['preco'];

                    if ($quantidadeVenda <= $quantidadeRestante) {
                        // Comprar toda a quantidade desta venda
                        $this->info("Comprado {$quantidadeVenda} ações a {$precoVenda}");
                        $quantidadeRestante -= $quantidadeVenda;
                        unset($ordensVenda[$index]); // Remove a ordem de venda que foi completamente preenchida
                    } else {
                        // Comprar parcialmente e atualizar o restante
                        $this->info("Comprado {$quantidadeRestante} ações a {$precoVenda}");
                        $ordensVenda[$index]['quantidade'] = $quantidadeVenda - $quantidadeRestante;
                        $quantidadeRestante = 0; // Tudo foi comprado
                    }
                } else {
                    break; // Já comprou tudo que precisava
                }
            }

            // Reescreve o arquivo vendas.txt com as ordens restantes
            $conteudo = '';
            foreach ($ordensVenda as $vendaRestante) {
                $conteudo .= "{$vendaRestante['quantidade']} @ {$vendaRestante['preco']}\n";
            }
            file_put_contents($caminhoVendas, $conteudo);

            $this->info("Ordem de mercado de compra de {$qty} ações realizada.");
        } else {
            $this->info('Operação não suportada para ordens de venda do tipo market.');
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
