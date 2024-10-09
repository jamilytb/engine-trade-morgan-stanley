<?php

namespace App\Services;

class OrderBook
{
    private static $compraOrdem = [];
    private static $vendeOrdem = [];
    
    // Ativo fixo
    private static $ativo = 'AAPL';
    private static $price = 50.00;

    public static function adicionarOrdemCompra($price, $qty)
    {
        self::$compraOrdem[] = ['price' => $price, 'qty' => $qty];
    }

    public static function adicionarOrdemVenda($price, $qty)
    {
        self::$vendeOrdem[] = ['price' => $price, 'qty' => $qty];
    }

    public static function ordemCompra()
    {
        return self::$compraOrdem;
    }

    public static function ordemVenda()
    {
        return self::$vendeOrdem;
    }

    public static function atualizaOrdemVenda($ordem)
    {
        self::$vendeOrdem = $ordem;
    }

    public static function atualizaOrdemCompra($ordem)
    {
        self::$compraOrdem = $ordem;
    }

    public static function obterPrecoAtivo()
    {
        return self::$price; // Retorna o preço fixo do ativo
    }
}
