<?php

namespace Database\Seeders;

use App\Models\Ordem;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class OrdemSeeder extends Seeder
{
    public function run()
    {
        //Criando ordens de compra
        Ordem::create([
            'type' => 'buy',
            'price' => 50.00,
            'qty' => 100,
        ]);

        Ordem::create([
            'type' => 'buy',
            'price' => 45.00,
            'qty' => 200,
        ]);

        //Criando ordens de venda
        Ordem::create([
            'type' => 'sell',
            'price' => 50.00,
            'qty' => 100,
        ]);

        Ordem::create([
            'type' => 'sell',
            'price' => 55.00,
            'qty' => 150,
        ]);
    }
}
