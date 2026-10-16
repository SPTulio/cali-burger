<?php
// Carregado no início de todas as páginas: configuração, sessão segura,
// banco de dados, autenticação e proteção CSRF.

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/cardapio.php';
require_once __DIR__ . '/pedidos.php';
require_once __DIR__ . '/funcionarios.php';

ini_set('display_errors', MODO_DEBUG ? '1' : '0');
error_reporting(E_ALL);

// Sessão: cookie inacessível ao JavaScript e não enviado por outros sites.
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'use_strict_mode' => true,
    ]);
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');

// Erros inesperados viram uma página amigável (e ficam no log do servidor).
set_exception_handler(function (Throwable $e): void {
    erro_fatal('Ocorreu um erro inesperado. Tente novamente.', $e);
});

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
}
