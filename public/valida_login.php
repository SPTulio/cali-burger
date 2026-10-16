<?php
// Login do cliente (usuário e senha).
require_once __DIR__ . '/../app/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirecionar('login.php');
}
if (login_bloqueado()) {
    mensagem('Muitas tentativas. Aguarde alguns minutos e tente novamente.', 'erro');
    redirecionar('login.php');
}

$login = campo('login');
$senha = (string) ($_POST['senha'] ?? '');
$cliente = consultar_um('SELECT id, nome, senha FROM cliente WHERE login = ?', [$login]);

$ok = $cliente && conferir_senha($senha, $cliente['senha'], function (string $hash) use ($cliente) {
    alterar('UPDATE cliente SET senha = ? WHERE id = ?', [$hash, (int) $cliente['id']]);
});

if (!$ok) {
    registrar_falha_login();
    mensagem('Usuário ou senha inválidos.', 'erro');
    redirecionar('login.php');
}

entrar(PERFIL_CLIENTE, (int) $cliente['id'], $cliente['nome']);
$destino = $_SESSION['voltar_para'] ?? PAGINA_INICIAL[PERFIL_CLIENTE];
unset($_SESSION['voltar_para']);
redirecionar(preg_match('/^[a-z_]+\.php$/', $destino) ? $destino : PAGINA_INICIAL[PERFIL_CLIENTE]);
