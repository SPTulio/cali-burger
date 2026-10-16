<?php
// Cria o pedido (POST vindo do pagamento) e mostra a confirmação.
require_once __DIR__ . '/../app/bootstrap.php';
$usuario = exigir_perfil(PERFIL_CLIENTE);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $numero = criar_pedido($usuario, $_SESSION['carrinho'] ?? [], $_SESSION['observacoes'] ?? []);
    } catch (EstoqueInsuficiente | InvalidArgumentException $e) {
        mensagem($e->getMessage(), 'erro');
        redirecionar('carrinho.php');
    }
    unset($_SESSION['carrinho'], $_SESSION['observacoes']);
    // Redireciona para não criar o pedido de novo se a página for recarregada.
    redirecionar('pedido_sucesso.php?pedido=' . $numero);
}

$numero = campo_inteiro('pedido', $_GET) ?? 0;
$pedido = consultar_um(
    'SELECT numero_do_pedido, valor FROM pedido WHERE numero_do_pedido = ? AND id_cliente = ?',
    [$numero, (int) $usuario['id']]
);
if (!$pedido) {
    redirecionar('meus_pedidos.php');
}

pagina_inicio('Pedido realizado', ['menu' => PERFIL_CLIENTE, 'estilo' => '
    .sucesso { max-width: 480px; }
    .sucesso .icone { font-size: 3em; }
    .sucesso h2 { color: #27ae60 !important; }
    .sucesso .acoes { margin-top: 25px; }
']);
?>
<main class="container sucesso">
    <div class="icone" aria-hidden="true">✅</div>
    <h2>Pedido realizado com sucesso!</h2>
    <p>Pedido nº <strong><?= (int) $pedido['numero_do_pedido'] ?></strong> · <?= dinheiro($pedido['valor']) ?></p>
    <p>Assim que a lanchonete aceitar, ele vai para a cozinha. Acompanhe o status em "Meus pedidos".</p>
    <div class="acoes">
        <a href="meus_pedidos.php" class="botao">Acompanhar pedido</a>
        <a href="cardapio_cliente.php" class="botao botao-secundario">Voltar ao cardápio</a>
    </div>
</main>
<?php
pagina_fim();
