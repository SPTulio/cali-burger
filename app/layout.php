<?php
// Cabeçalho e rodapé HTML compartilhados por todas as páginas internas.

declare(strict_types=1);

/**
 * Abre a página: <head>, menu do perfil logado e mensagens pendentes.
 *
 * @param string $titulo  título da aba do navegador
 * @param array  $opcoes  'css' => [arquivos extras], 'estilo' => CSS da página, 'menu' => perfil do menu
 */
function pagina_inicio(string $titulo, array $opcoes = []): void
{
    $usuario = usuario_logado();
    $menu = $opcoes['menu'] ?? ($usuario['perfil'] ?? PERFIL_CLIENTE);
    ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <title><?= e($titulo) ?> - Cali Burger</title>
    <link rel="icon" href="imagens/logo_cali_ico.ico" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <?php foreach ($opcoes['css'] ?? [] as $css): ?>
        <link rel="stylesheet" href="css/<?= e($css) ?>">
    <?php endforeach; ?>
    <?php if (!empty($opcoes['estilo'])): ?>
        <style><?= $opcoes['estilo'] ?></style>
    <?php endif; ?>
</head>
<body>
    <?php require __DIR__ . '/partials/menu_' . $menu . '.php'; ?>
    <div class="mensagens"><?= exibir_mensagens() ?></div>
    <?php
}

function pagina_fim(): void
{
    ?>
    <div id="modal-confirmacao" class="modal" hidden>
        <div class="modal-content" role="dialog" aria-modal="true" aria-labelledby="modal-texto">
            <p id="modal-texto">Confirma a ação?</p>
            <div class="modal-botoes">
                <button type="button" class="botao" id="modal-confirmar">Confirmar</button>
                <button type="button" class="botao botao-secundario" id="modal-cancelar">Cancelar</button>
            </div>
        </div>
    </div>
    <script src="js/confirmacao.js"></script>
</body>
</html>
    <?php
}

/** Página de login/cadastro (layout com o logo à esquerda). */
function pagina_login_inicio(string $titulo): void
{
    ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($titulo) ?> - Cali Burger</title>
    <link rel="icon" href="imagens/logo_cali_ico.ico" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/login.css">
</head>
<body>
<div class="container">
    <div class="left-side">
        <img src="imagens/logo_cali_sem_fundo.png" alt="Logo Cali Burger" class="logo">
    </div>
    <div class="right-side">
    <?php
}

function pagina_login_fim(): void
{
    ?>
    </div>
</div>
</body>
</html>
    <?php
}

/** Botão que envia um POST com confirmação (ex.: excluir). */
function botao_post(string $acao, array $campos, string $texto, string $confirmacao = '', string $classe = 'botao'): string
{
    $html = '<form method="POST" action="' . e($acao) . '" class="form-acao"'
        . ($confirmacao !== '' ? ' data-confirmar="' . e($confirmacao) . '"' : '') . '>' . csrf_campo();
    foreach ($campos as $nome => $valor) {
        $html .= '<input type="hidden" name="' . e($nome) . '" value="' . e($valor) . '">';
    }
    return $html . '<button type="submit" class="' . e($classe) . '">' . e($texto) . '</button></form>';
}
