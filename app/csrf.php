<?php
// Proteção contra CSRF: todo formulário POST leva um token secreto da
// sessão. Um site malicioso não consegue saber o token, então não consegue
// enviar formulários em nome do usuário logado.

declare(strict_types=1);

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_campo(): string
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function verificar_csrf(): void
{
    $enviado = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($enviado) || !hash_equals(csrf_token(), $enviado)) {
        http_response_code(400);
        echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><title>Sessão expirada</title></head>'
            . '<body style="font-family: sans-serif; text-align: center; padding: 60px;">'
            . '<h1>Sessão expirada</h1><p>Recarregue a página e tente novamente.</p>'
            . '<p><a href="index.php">Voltar ao início</a></p></body></html>';
        exit();
    }
}
