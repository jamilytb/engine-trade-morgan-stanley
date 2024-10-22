<?php

namespace App\Models;

class Ordem
{
    public $preco;
    public $quantidade;
    public $id; // Adicione esta linha
    public $historico = []; // Armazena o histórico de ordens de mercado

    public function __construct($preco = null, $quantidade = null, $id = null) // Inclua o ID no construtor
    {
        $this->preco = $preco;
        $this->quantidade = $quantidade;
        $this->id = $id; // Inicializa o ID
    }

    public function atualizar($preco, $quantidade, $id)
    {
        $this->preco = $preco;
        $this->quantidade = $quantidade;
        $this->id = $id; // Armazena o ID na ordem
    }

    public function limpar()
    {
        $this->preco = null;
        $this->quantidade = null;
        $this->id = null; // Limpa o ID também
    }

    public function existe()
    {
        return $this->preco !== null && $this->quantidade !== null;
    }

    public function adicionarHistorico($quantidade, $preco)
    {
        $this->historico[] = ['quantidade' => $quantidade, 'preco' => $preco];
    }

    public function mostrarHistorico()
    {
        return $this->historico;
    }
}
