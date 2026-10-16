<?php
// Autenticação e controle de acesso por perfil.
//
// O sistema tem três perfis, cada um com sua tabela e sua área:
//   - cliente:    faz pedidos pelo cardápio
//   - balconista: (dono) gerencia pedidos, cardápio, estoque e funcionários
//   - cozinheiro: prepara os pedidos aceitos

declare(strict_types=1);

const PERFIL_CLIENTE = 'cliente';
const PERFIL_BALCONISTA = 'balconista';
const PERFIL_COZINHEIRO = 'cozinheiro';

const PAGINA_LOGIN = [
    PERFIL_CLIENTE => 'login.php',
    PERFIL_BALCONISTA => 'login_balconista.php',
    PERFIL_COZINHEIRO => 'login_cozinheiro.php',
];

const PAGINA_INICIAL = [
    PERFIL_CLIENTE => 'cardapio_cliente.php',
    PERFIL_BALCONISTA => 'pedidos.php',
    PERFIL_COZINHEIRO => 'pedidos_cozinheiro.php',
];

/** Usuário logado: ['perfil' => ..., 'id' => ..., 'nome' => ...], ou null. */
function usuario_logado(): ?array
{
    return $_SESSION['usuario'] ?? null;
}

function entrar(string $perfil, string|int $id, string $nome): void
{
    // Novo id de sessão a cada login evita sequestro de sessão (session fixation).
    session_regenerate_id(true);
    $_SESSION['usuario'] = ['perfil' => $perfil, 'id' => $id, 'nome' => $nome];
    unset($_SESSION['tentativas_login']);
}

function sair(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/**
 * Garante que há um usuário logado com um dos perfis informados.
 * Sem login: vai para a tela de login. Perfil errado: acesso negado (403).
 */
function exigir_perfil(string ...$perfis): array
{
    $usuario = usuario_logado();
    if ($usuario === null) {
        if (in_array(PERFIL_CLIENTE, $perfis, true) && $_SERVER['REQUEST_METHOD'] === 'GET') {
            $_SESSION['voltar_para'] = basename($_SERVER['SCRIPT_NAME']);
        }
        redirecionar(PAGINA_LOGIN[$perfis[0]]);
    }
    if (!in_array($usuario['perfil'], $perfis, true)) {
        pagina_proibida();
    }
    return $usuario;
}

/** Nas telas de login: quem já está logado vai direto para a sua área. */
function redirecionar_se_logado(): void
{
    $usuario = usuario_logado();
    if ($usuario !== null) {
        redirecionar(PAGINA_INICIAL[$usuario['perfil']]);
    }
}

/**
 * Confere a senha digitada. Aceita senhas antigas gravadas em texto puro
 * (versão anterior do sistema) e, nesse caso, converte para hash na hora.
 */
function conferir_senha(string $digitada, string $armazenada, callable $salvarNovoHash): bool
{
    $info = password_get_info($armazenada);
    if ($info['algo'] !== null && $info['algo'] !== 0) {
        if (!password_verify($digitada, $armazenada)) {
            return false;
        }
        if (password_needs_rehash($armazenada, PASSWORD_DEFAULT)) {
            $salvarNovoHash(password_hash($digitada, PASSWORD_DEFAULT));
        }
        return true;
    }
    // Senha legada em texto puro.
    if ($armazenada !== '' && hash_equals($armazenada, $digitada)) {
        $salvarNovoHash(password_hash($digitada, PASSWORD_DEFAULT));
        return true;
    }
    return false;
}

// ---------------------------------------------------- Limite de tentativas

const MAX_TENTATIVAS_LOGIN = 5;
const BLOQUEIO_LOGIN_SEGUNDOS = 300;

function login_bloqueado(): bool
{
    $t = $_SESSION['tentativas_login'] ?? null;
    if (!$t) {
        return false;
    }
    if (time() - $t['inicio'] > BLOQUEIO_LOGIN_SEGUNDOS) {
        unset($_SESSION['tentativas_login']);
        return false;
    }
    return $t['quantidade'] >= MAX_TENTATIVAS_LOGIN;
}

function registrar_falha_login(): void
{
    $_SESSION['tentativas_login'] ??= ['quantidade' => 0, 'inicio' => time()];
    $_SESSION['tentativas_login']['quantidade']++;
}

/**
 * Login de funcionário (balconista ou cozinheiro), feito pelo CPF.
 * Retorna a mensagem de erro, ou null se entrou com sucesso.
 */
function login_funcionario(string $perfil, string $tabela, string $cpf, string $senha): ?string
{
    if (login_bloqueado()) {
        return 'Muitas tentativas. Aguarde alguns minutos e tente novamente.';
    }
    $cpf = somente_digitos($cpf);
    $funcionario = valida_cpf($cpf)
        ? consultar_um("SELECT cpf, nome, senha FROM $tabela WHERE cpf = ?", [$cpf])
        : null;
    $ok = $funcionario && conferir_senha($senha, $funcionario['senha'], function (string $hash) use ($tabela, $cpf) {
        alterar("UPDATE $tabela SET senha = ? WHERE cpf = ?", [$hash, $cpf]);
    });
    if (!$ok) {
        registrar_falha_login();
        // Mesma mensagem para CPF inexistente e senha errada: não revela quem tem conta.
        return 'CPF ou senha inválidos.';
    }
    entrar($perfil, $funcionario['cpf'], $funcionario['nome']);
    return null;
}

// ---------------------------------------------------------- Troca de senha

/**
 * Troca a senha se o usuário preencheu "nova senha" no formulário de perfil.
 * Exige a senha atual. Retorna a mensagem de erro, ou null se deu certo
 * (ou se não havia troca a fazer).
 */
function processar_troca_senha(string $tabela, string $colunaId, string|int $id): ?string
{
    $nova = (string) ($_POST['nova_senha'] ?? '');
    if ($nova === '') {
        return null;
    }
    if (!valida_senha($nova)) {
        return 'A nova senha deve ter pelo menos 6 caracteres.';
    }
    if ($nova !== ($_POST['confirmar_nova_senha'] ?? '')) {
        return 'A confirmação da nova senha não confere.';
    }
    $atual = consultar_um("SELECT senha FROM $tabela WHERE $colunaId = ?", [$id]);
    if (!$atual || !conferir_senha((string) ($_POST['senha_atual'] ?? ''), $atual['senha'], fn() => null)) {
        return 'A senha atual está incorreta.';
    }
    alterar("UPDATE $tabela SET senha = ? WHERE $colunaId = ?", [password_hash($nova, PASSWORD_DEFAULT), $id]);
    return null;
}

/** Campos de troca de senha usados nos formulários de perfil. */
function html_campos_troca_senha(): string
{
    return '<fieldset class="linha-inteira"><legend>Trocar senha (opcional)</legend>'
        . '<div class="formulario duas-colunas">'
        . '<label>Senha atual<input type="password" name="senha_atual" autocomplete="current-password"></label>'
        . '<span></span>'
        . '<label>Nova senha<input type="password" name="nova_senha" minlength="6" autocomplete="new-password"></label>'
        . '<label>Confirmar nova senha<input type="password" name="confirmar_nova_senha" minlength="6" autocomplete="new-password"></label>'
        . '</div></fieldset>';
}
