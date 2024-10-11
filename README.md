# Sistema de Gerenciamento de Ordens de Compra e Venda

Este projeto gerencia ordens de compra e venda, desenvolvido em PHP com Laravel. O sistema permite a criação de ordens do tipo "limit" e "market", além de fazer o casamento entre elas quando posível, chamado "trade". 

## Tecnologias

- **PHP**
- **Laravel**
- **Laragon**

## Instalação

Para configurar o projeto em sua máquina local, siga os passos abaixo:

1. Baixe e instale o Laragon `https://laragon.org/download/`.

2. Baixe e instale a linguagem PHP 8.3.9 `https://www.php.net/downloads.php`.

3. Clone o repositório com o comando `git clone <URL>`. 

4. Instale as dependências do Composer para que o projeto funcione `composer install`.

## Executando o comando para rodar o projeto

1. Abra o terminal no diretório raiz do projeto.

2. Digite o seguinte comando para iniciar a aplicação:

   ```bash
   php artisan iniciar
   
3. Após executar o comando, o menu abaixo será exibido:

   ![menu](https://github.com/user-attachments/assets/0711f2ae-68e4-4c0c-b855-b4bbcfbda4bb)

## Arquivos e Localização dos Códigos 
Os códigos desenvolvidos para a engine de processamento de ordens estão organizados nos seguintes arquivos: - **Comando para processar ordens**: `app/Console/Commands/ProcessandoOrdens.php` - Este arquivo contém o comando `iniciar`, que executa o loop contínuo de processamento de ordens de compra e venda, com um menu interativo no console. - **Modelo de Ordens**: `app/Models/Ordem.php` - Define a estrutura das ordens de compra e venda no sistema. - **Serviço de Livro de Ordens**: `app/Services/OrderBook.php` - Contém a lógica para armazenar, atualizar e processar ordens de compra e venda, além de simular a execução das ordens com base no preço do ativo. Esses arquivos juntos implementam a lógica de uma matching engine básica, que processa ordens de compra e venda com base no input do usuário.
