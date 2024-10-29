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

   ![menu](https://github.com/user-attachments/assets/71ddd0fb-a01c-44d5-996f-f78bcb4522d1)

## Arquivos e Localização dos Códigos 

Os códigos desenvolvidos estão organizados nos seguintes arquivos: 

**Comando para processar ordens**: `app/Console/Commands/ProcessandoOrdens.php`
- Este arquivo tem o comando `iniciar`, que executa o programa e roda as ordens de compra e venda com um menu interativo no console.
  
**Modelo de Ordens**: `app/Models/Ordem.php`
- Define a estrutura das ordens de compra e venda no sistema.
