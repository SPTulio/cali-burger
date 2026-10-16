<?php
require_once __DIR__ . '/../app/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirecionar('login_balconista.php');
}

$erro = login_funcionario(PERFIL_BALCONISTA, 'balconista_dono', campo('cpf'), (string) ($_POST['senha'] ?? ''));
if ($erro !== null) {
    mensagem($erro, 'erro');
    redirecionar('login_balconista.php');
}
redirecionar(PAGINA_INICIAL[PERFIL_BALCONISTA]);
