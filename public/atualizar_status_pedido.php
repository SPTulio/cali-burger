<?php
// Muda o status de um pedido. As regras (quem pode fazer o quê) ficam em
// app/pedidos.php e são conferidas no servidor, não só na tela.
require_once __DIR__ . '/../app/bootstrap.php';
$usuario = exigir_perfil(PERFIL_BALCONISTA, PERFIL_COZINHEIRO, PERFIL_CLIENTE);

$paginasPermitidas = ['pedidos.php', 'pedidos_cozinheiro.php', 'meus_pedidos.php'];
$voltar = campo('voltar');
if (!in_array(strtok($voltar, '?'), $paginasPermitidas, true)) {
    $voltar = PAGINA_INICIAL[$usuario['perfil']];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirecionar($voltar);
}

try {
    mensagem(alterar_status_pedido(campo_inteiro('numero_do_pedido') ?? 0, campo('acao'), $usuario));
} catch (InvalidArgumentException $e) {
    mensagem($e->getMessage(), 'erro');
}
redirecionar($voltar);
