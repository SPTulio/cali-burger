<?php
// Itens do cardápio para o balconista: lista, disponibilidade e exclusão.
require_once __DIR__ . '/../app/bootstrap.php';
exigir_perfil(PERFIL_BALCONISTA);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && campo('action') === 'delete') {
    $id = campo_inteiro('id') ?? 0;
    // Os ingredientes da receita saem junto (ON DELETE CASCADE). Pedidos antigos
    // guardam o nome do produto, então o histórico continua correto.
    $excluidos = alterar('DELETE FROM cardapio WHERE id = ?', [$id]);
    mensagem($excluidos ? 'Item excluído do cardápio.' : 'Item não encontrado.', $excluidos ? 'sucesso' : 'erro');
    redirecionar('cardapio.php');
}

$itens = listar_cardapio();
pagina_inicio('Cardápio');
?>
<div class="container largo">
    <h2>Itens do cardápio</h2>
    <p><a href="adiciona_item_cardapio.php" class="botao">Adicionar novo item</a></p>
    <?php require __DIR__ . '/../app/partials/tabela_cardapio.php'; ?>
</div>
<?php
pagina_fim();
