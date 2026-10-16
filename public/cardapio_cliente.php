<?php
// Cardápio para o cliente: navegar por categoria e adicionar itens ao carrinho.
require_once __DIR__ . '/../app/bootstrap.php';

$categorias = listar_categorias();
$categoriaSelecionada = campo_inteiro('categoria', $_GET);
$itens = listar_cardapio($categoriaSelecionada ?: null);

pagina_inicio('Cardápio', ['menu' => PERFIL_CLIENTE, 'estilo' => '
    .cardapio-container { display: flex; flex-direction: column; align-items: center; gap: 20px; padding: 20px; }
    .cardapio-container form { width: 100%; max-width: 850px; }
    .item-cardapio { border: 1px solid #e5e5e5; padding: 20px 25px; margin: 12px 0; border-radius: 12px; width: 100%;
        background-color: #fff; box-shadow: 0 2px 8px rgba(0,0,0,0.06); display: flex; align-items: center; gap: 20px; }
    .item-cardapio.esgotado { opacity: 0.6; }
    .item-cardapio .item-img { height: 90px; width: 90px; object-fit: cover; border-radius: 10px; margin: 0; }
    .item-info { flex: 1; }
    .item-info h3 { margin-bottom: 4px; }
    .item-info p { color: #666; font-size: 0.95em; margin-bottom: 6px; }
    .item-quantidade { display: flex; flex-direction: column; align-items: center; gap: 6px; }
    .item-quantidade label { font-size: 0.8em; color: #777; }
    .item-quantidade input[type="number"] { width: 70px; padding: 6px; font-size: 1em; border: 1px solid #ccc; border-radius: 6px; text-align: center; }
    nav.category-nav { background-color: #b22222; padding: 10px 0; gap: 10px; }
    nav.category-nav a { font-weight: 400; }
    nav.category-nav a.selected { background-color: #fff; color: #b22222; font-weight: 700; }
    .botao-adicionar { position: sticky; bottom: 0; padding: 15px 0; background: linear-gradient(transparent, #f9f9f9 40%); text-align: center; }
    .botao-adicionar button { font-size: 1.1em; padding: 12px 40px; }
']);
?>
<nav class="category-nav" aria-label="Categorias">
    <a href="cardapio_cliente.php" class="<?= $categoriaSelecionada ? '' : 'selected' ?>">Todos</a>
    <?php foreach ($categorias as $cat): ?>
        <a href="cardapio_cliente.php?categoria=<?= (int) $cat['id'] ?>" class="<?= $categoriaSelecionada === (int) $cat['id'] ? 'selected' : '' ?>">
            <?= e($cat['nome']) ?>
        </a>
    <?php endforeach; ?>
</nav>

<main class="cardapio-container">
    <form method="POST" action="carrinho.php">
        <?= csrf_campo() ?>
        <input type="hidden" name="acao" value="adicionar">
        <input type="hidden" name="categoria" value="<?= (int) $categoriaSelecionada ?>">

        <?php if (!$itens): ?>
            <p>Nenhum item nesta categoria.</p>
        <?php endif; ?>

        <?php foreach ($itens as $item): ?>
            <?php $imagem = url_imagem($item['imagem']); $maximo = min(MAX_QUANTIDADE_ITEM, $item['porcoes'] ?? MAX_QUANTIDADE_ITEM); ?>
            <div class="item-cardapio <?= $item['disponivel'] ? '' : 'esgotado' ?>">
                <?php if ($imagem): ?>
                    <img src="<?= $imagem ?>" alt="<?= e($item['nome']) ?>" class="item-img" loading="lazy">
                <?php endif; ?>
                <div class="item-info">
                    <h3><?= e($item['nome']) ?></h3>
                    <p><?= e($item['descricao']) ?></p>
                    <strong><?= dinheiro($item['preco']) ?></strong>
                </div>
                <div class="item-quantidade">
                    <?php if ($item['disponivel']): ?>
                        <label for="qtd-<?= (int) $item['id'] ?>">Quantidade</label>
                        <input type="number" id="qtd-<?= (int) $item['id'] ?>" name="quantidade[<?= (int) $item['id'] ?>]"
                               value="0" min="0" max="<?= (int) $maximo ?>">
                    <?php else: ?>
                        <?= html_disponibilidade($item) ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if ($itens): ?>
            <div class="botao-adicionar">
                <button type="submit">Adicionar ao carrinho</button>
            </div>
        <?php endif; ?>
    </form>
</main>
<?php
pagina_fim();
