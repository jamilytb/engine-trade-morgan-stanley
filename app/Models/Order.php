<?php

namespace App\Models;

class Order
{
    public string $tipo;
    public string $lado;
    public ?float $preco;
    public int $quantidade;
    public string $id;

    public function __construct(string $tipo, string $lado, ?float $preco, int $quantidade)
    {
        $this->tipo = $tipo;
        $this->lado = $lado;
        $this->preco = $preco;
        $this->quantidade = $quantidade;
        $this->id = uniqid();
    }
}
