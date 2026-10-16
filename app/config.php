<?php
// Configurações do sistema. Os valores padrão funcionam no XAMPP
// (MySQL em localhost, usuário root sem senha). No Docker, eles vêm
// das variáveis de ambiente definidas no docker-compose.yml / .env.

declare(strict_types=1);

function env(string $nome, string $padrao): string
{
    $valor = getenv($nome);
    return ($valor === false || $valor === '') ? $padrao : $valor;
}

define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_PORT', (int) env('DB_PORT', '3306'));
define('DB_USUARIO', env('DB_USUARIO', 'root'));
define('DB_SENHA', getenv('DB_SENHA') === false ? '' : (string) getenv('DB_SENHA'));
define('DB_NOME', env('DB_NOME', 'db_caliburger'));

// Mostrar erros detalhados na tela (somente em desenvolvimento).
define('MODO_DEBUG', env('MODO_DEBUG', '0') === '1');

// Ingrediente com quantidade igual ou menor que esta aparece como "estoque baixo".
define('ESTOQUE_BAIXO', (int) env('ESTOQUE_BAIXO', '10'));

// Quantidade máxima de um mesmo item no carrinho.
define('MAX_QUANTIDADE_ITEM', 20);

// Imagens dos produtos.
define('PASTA_IMAGENS_LANCHES', dirname(__DIR__) . '/public/imagens/lanches');
define('TAMANHO_MAXIMO_IMAGEM', 2 * 1024 * 1024);

date_default_timezone_set(env('FUSO_HORARIO', 'America/Sao_Paulo'));
