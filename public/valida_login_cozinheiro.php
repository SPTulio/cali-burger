<?php
require_once __DIR__ . '/../app/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirecionar('login_cozinheiro.php');
}

$erro = login_funcionario(PERFIL_COZINHEIRO, 'cozinheiro', campo('cpf'), (string) ($_POST['senha'] ?? ''));
if ($erro !== null) {
    mensagem($erro, 'erro');
    redirecionar('login_cozinheiro.php');
}
redirecionar(PAGINA_INICIAL[PERFIL_COZINHEIRO]);
