<?php
require_once __DIR__ . '/../app/bootstrap.php';
$usuario = exigir_perfil(PERFIL_BALCONISTA);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirecionar('main.php');
}

$cpf = somente_digitos(campo('cpf'));
$total = (int) consultar_um('SELECT COUNT(*) AS total FROM balconista_dono')['total'];

if ($total <= 1) {
    mensagem('Não é possível excluir o último balconista: o sistema ficaria sem ninguém para gerenciá-lo.', 'erro');
    redirecionar('main.php');
}
if (alterar('DELETE FROM balconista_dono WHERE cpf = ?', [$cpf]) === 0) {
    mensagem('Balconista não encontrado.', 'erro');
    redirecionar('main.php');
}

if ($cpf === $usuario['id']) {
    // Excluiu a própria conta pelo perfil.
    sair();
    session_start();
    mensagem('Sua conta foi excluída.');
    redirecionar('login_balconista.php');
}
mensagem('Balconista excluído.');
redirecionar('main.php');
