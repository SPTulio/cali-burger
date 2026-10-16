<?php
// Exclui a conta do cliente logado. Os pedidos antigos continuam no
// histórico da lanchonete (sem vínculo com a conta).
require_once __DIR__ . '/../app/bootstrap.php';
$usuario = exigir_perfil(PERFIL_CLIENTE);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirecionar('perfil_cliente.php');
}

alterar('DELETE FROM cliente WHERE id = ?', [(int) $usuario['id']]);
sair();
session_start();
mensagem('Sua conta foi excluída.');
redirecionar('login.php');
