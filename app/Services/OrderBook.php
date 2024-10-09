<?php

namespace App\Services;

class OrderBook
{
    private static $buyOrders = [];
    private static $sellOrders = [];
    
    // Ativo fixo
    private static $asset = 'AAPL';
    private static $price = 50.00;

    public static function addBuyOrder($price, $qty)
    {
        self::$buyOrders[] = ['price' => $price, 'qty' => $qty];
    }

    public static function addSellOrder($price, $qty)
    {
        self::$sellOrders[] = ['price' => $price, 'qty' => $qty];
    }

    public static function getBuyOrders()
    {
        return self::$buyOrders;
    }

    public static function getSellOrders()
    {
        return self::$sellOrders;
    }

    public static function updateSellOrders($orders)
    {
        self::$sellOrders = $orders;
    }

    public static function updateBuyOrders($orders)
    {
        self::$buyOrders = $orders;
    }

    public static function getAssetPrice()
    {
        return self::$price; // Retorna o preço fixo do ativo
    }
}
