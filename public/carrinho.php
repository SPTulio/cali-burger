<?php
// Carrinho de compras (guardado na sessão até o pedido ser finalizado).
require_once __DIR__ . '/../app/bootstrap.php';

$_SESSION['carrinho'] = normalizar_carrinho($_SESSION['carrinho'] ?? []);
$_SESSION['observacoes'] ??= [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = campo('acao');
    $quantidades = is_array($_POST['quantidade'] ?? null) ? $_POST['quantidade'] : [];

    if ($acao === 'adicionar') {
        // Soma ao que já está no carrinho (antes, adicionar de outra categoria apagava o carrinho).
        $adicionados = 0;
        foreach (normalizar_carrinho($quantidades) as $id => $qtd) {
            $_SESSION['carrinho'][$id] = min(MAX_QUANTIDADE_ITEM, ($_SESSION['carrinho'][$id] ?? 0) + $qtd);
            $adicionados += $qtd;
        }
        mensagem($adicionados > 0
            ? "$adicionados item(ns) adicionado(s) ao carrinho."
            : 'Escolha a quantidade de pelo menos um item.', $adicionados > 0 ? 'sucesso' : 'erro');
        $categoria = campo_inteiro('categoria');
        redirecionar('cardapio_cliente.php' . ($categoria ? '?categoria=' . $categoria : ''));
    }

    if ($acao === 'atualizar' || $acao === 'finalizar') {
        $_SESSION['carrinho'] = normalizar_carrinho($quantidades);
        $observacoes = is_array($_POST['observacao'] ?? null) ? $_POST['observacao'] : [];
        $_SESSION['observacoes'] = [];
        foreach ($_SESSION['carrinho'] as $id => $qtd) {
            $obs = trim((string) ($observacoes[$id] ?? ''));
            if ($obs !== '') {
                $_SESSION['observacoes'][$id] = mb_substr($obs, 0, 255);
            }
        }
        if ($acao === 'finalizar' && $_SESSION['carrinho']) {
            redirecionar('pagamento.php');
        }
        mensagem('Carrinho atualizado.');
        redirecionar('carrinho.php');
    }

    if (isset($_POST['remover'])) {
        $id = campo_inteiro('remover');
        unset($_SESSION['carrinho'][$id], $_SESSION['observacoes'][$id]);
        mensagem('Item removido do carrinho.');
        redirecionar('carrinho.php');
    }
    redirecionar('carrinho.php');
}

// Busca nome e preço atuais dos itens do carrinho.
$itens = [];
$total = 0;
if ($_SESSION['carrinho']) {
    $ids = array_keys($_SESSION['carrinho']);
    $produtos = consultar('SELECT id, nome, preco FROM cardapio WHERE id IN (' . marcadores(count($ids)) . ')', $ids);
    foreach ($produtos as $p) {
        $id = (int) $p['id'];
        $qtd = $_SESSION['carrinho'][$id];
        $subtotal = centavos($p['preco']) * $qtd;
        $total += $subtotal;
        $itens[] = ['id' => $id, 'nome' => $p['nome'], 'preco' => $p['preco'], 'quantidade' => $qtd,
            'subtotal' => $subtotal / 100, 'observacao' => $_SESSION['observacoes'][$id] ?? ''];
    }
    if (count($produtos) !== count($ids)) {
        // Itens que saíram do cardápio são retirados do carrinho.
        $_SESSION['carrinho'] = array_intersect_key($_SESSION['carrinho'], array_flip(array_column($itens, 'id')));
        mensagem('Um item que não está mais no cardápio foi retirado do seu carrinho.', 'erro');
    }
}

pagina_inicio('Carrinho', ['menu' => PERFIL_CLIENTE, 'estilo' => '
    .carrinho-container { max-width: 720px; text-align: left; }
    .carrinho-container h1 { text-align: center; }
    .item-carrinho { display: grid; grid-template-columns: 1fr auto; gap: 8px 16px; padding: 14px 0; border-bottom: 1px solid #eee; }
    .item-carrinho .nome { font-weight: 600; }
    .item-carrinho .preco { color: #777; font-size: 0.9em; }
    .item-carrinho .controles { display: flex; gap: 10px; align-items: center; }
    .item-carrinho input[type="number"] { width: 70px; padding: 6px; border: 1px solid #ccc; border-radius: 6px; text-align: center; }
    .item-carrinho input[type="text"] { grid-column: 1 / -1; padding: 8px 10px; border: 1px solid #ccc; border-radius: 6px; font-family: inherit; }
    .total-pedido { text-align: right; font-size: 1.2em; margin: 20px 0; }
    .rodape-carrinho { display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap; }
    .remover { background: none !important; color: #c0392b !important; padding: 0 !important; text-decoration: underline; font-weight: 400 !important; }
']);
?>
<main class="carrinho-container">
    <h1>Seu carrinho</h1>
    <?php if (!$itens): ?>
        <p style="text-align: center;">Seu carrinho está vazio.</p>
        <p style="text-align: center; margin-top: 20px;"><a href="cardapio_cliente.php" class="botao">Ver o cardápio</a></p>
    <?php else: ?>
        <form method="POST" action="carrinho.php" id="form-carrinho">
            <?= csrf_campo() ?>
            <!-- Botão padrão: apertar Enter em um campo atualiza o carrinho (e não remove um item). -->
            <button type="submit" name="acao" value="atualizar" tabindex="-1" aria-hidden="true"
                    style="position: absolute; left: -9999px;">Atualizar</button>
            <?php foreach ($itens as $item): ?>
                <div class="item-carrinho">
                    <div>
                        <div class="nome"><?= e($item['nome']) ?></div>
                        <div class="preco"><?= dinheiro($item['preco']) ?> cada · subtotal <?= dinheiro($item['subtotal']) ?></div>
                    </div>
                    <div class="controles">
                        <label class="texto-suave" for="qtd-<?= $item['id'] ?>">Qtd.</label>
                        <input type="number" id="qtd-<?= $item['id'] ?>" name="quantidade[<?= $item['id'] ?>]"
                               value="<?= (int) $item['quantidade'] ?>" min="0" max="<?= MAX_QUANTIDADE_ITEM ?>">
                        <button type="submit" name="remover" value="<?= $item['id'] ?>" class="remover">Remover</button>
                    </div>
                    <input type="text" name="observacao[<?= $item['id'] ?>]" value="<?= e($item['observacao']) ?>"
                           placeholder="Observação (ex.: sem cebola)" maxlength="255" aria-label="Observação para <?= e($item['nome']) ?>">
                </div>
            <?php endforeach; ?>

            <p class="total-pedido"><strong>Total do pedido: <?= dinheiro($total / 100) ?></strong></p>

            <div class="rodape-carrinho">
                <a href="cardapio_cliente.php" class="botao botao-secundario">Continuar comprando</a>
                <div class="acoes">
                    <button type="submit" name="acao" value="atualizar" class="botao-secundario">Atualizar carrinho</button>
                    <button type="submit" name="acao" value="finalizar" class="botao-sucesso">Finalizar pedido</button>
                </div>
            </div>
        </form>
    <?php endif; ?>
</main>
<?php
pagina_fim();
