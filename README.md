# Gerenciamento de Ordens

Este projeto é um sistema para gerenciar ordens de compra e venda, desenvolvido em PHP com Laravel. O sistema permite a criação de ordens do tipo "limit" e "market".

## Tecnologias

- **PHP**
- **Laravel**
- **MySQL** (ou outro banco de dados)

## Instalação

Para configurar o projeto em sua máquina local, siga os passos abaixo:

1. **Clone o repositório:**

   ```bash
   git clone https://github.com/usuario/seu-repositorio.git
   cd seu-repositorio
   ```

2. **Instale as dependências do Composer:**

   ```bash
   composer install
   ```

3. **Configure o ambiente:**

   Copie o arquivo `.env.example` para `.env` e ajuste as configurações do banco de dados.

   ```bash
   cp .env.example .env
   ```

4. **Gere a chave da aplicação:**

   ```bash
   php artisan key:generate
   ```

5. **Crie o banco de dados e execute as migrações:**

   ```bash
   php artisan migrate
   ```

6. **Inicie o servidor de desenvolvimento:**

   ```bash
   php artisan serve
   ```

   O projeto estará acessível em `http://localhost:8000`.


## Uso

Para criar uma nova ordem, você pode utilizar a classe `Ordem`. Aqui está um exemplo:

```php
use App\Models\Ordem;

$ordem = new Ordem('limit', 'compra', 100, 50.00);
```

## Contribuição

Contribuições são bem-vindas! Você pode abrir uma *issue* ou enviar um *pull request*.

## Licença

Este projeto está sob a licença MIT. Consulte o arquivo [LICENSE](LICENSE) para mais detalhes.
# engineMatching
#   e n g i n e - t r a d e - m o r g a n - s t a n l e y  
 #   e n g i n e - t r a d e - m o r g a n - s t a n l e y  
 