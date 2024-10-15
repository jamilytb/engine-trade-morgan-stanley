<?php

namespace App\Models;

class Ordem
{
    public $preco;
    public $quantidade;

    public function __construct($preco = null, $quantidade = null)
    {
        $this->preco = $preco;
        $this->quantidade = $quantidade;
    }

    public function atualizar($preco, $quantidade)
    {
        $this->preco = $preco;
        $this->quantidade = $quantidade;
    }

    public function limpar()
    {
        $this->preco = null;
        $this->quantidade = null;
    }

    public function existe()
    {
        return $this->preco !== null && $this->quantidade !== null;
    }
}
