<?php
// Cardápio (somente leitura) para a cozinha consultar receitas e disponibilidade.
require_once __DIR__ . '/../app/bootstrap.php';
exigir_perfil(PERFIL_COZINHEIRO);

$itens = listar_cardapio();
$somenteLeitura = true;
pagina_inicio('Cardápio');
?>
<div class="container largo">
    <h2>Itens do cardápio</h2>
    <?php require __DIR__ . '/../app/partials/tabela_cardapio.php'; ?>
</div>
<?php
pagina_fim();
