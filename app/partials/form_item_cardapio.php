<?php
// Formulário de item do cardápio (adicionar e editar).
// Espera $titulo, $item, $receita (id_ingrediente => qtd) e $erro.
$ingredientes = listar_ingredientes();
pagina_inicio($titulo);
?>
<div class="container estreito">
    <h2><?= e($titulo) ?></h2>
    <?php if ($erro): ?>
        <div class="alerta alerta-erro"><?= e($erro) ?></div>
    <?php endif; ?>
    <form method="POST" enctype="multipart/form-data" class="formulario duas-colunas">
        <?= csrf_campo() ?>
        <?php if (!empty($item['id'])): ?>
            <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
        <?php endif; ?>
        <label class="linha-inteira">Nome<input type="text" name="nome" value="<?= e($item['nome']) ?>" required maxlength="100"></label>
        <label class="linha-inteira">Descrição<input type="text" name="descricao" value="<?= e($item['descricao']) ?>" maxlength="255"></label>
        <label>Categoria
            <select name="categoria" required>
                <option value="">Selecione</option>
                <?php foreach (listar_categorias() as $cat): ?>
                    <option value="<?= (int) $cat['id'] ?>" <?= (int) $item['categoria_id'] === (int) $cat['id'] ? 'selected' : '' ?>><?= e($cat['nome']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Preço (R$)<input type="text" name="preco" value="<?= e(is_numeric($item['preco']) ? number_format((float) $item['preco'], 2, ',', '') : $item['preco']) ?>" inputmode="decimal" placeholder="22,90" required></label>
        <label class="linha-inteira">Imagem (PNG, JPG ou WebP, até 2 MB)
            <input type="file" name="imagem" accept="image/png,image/jpeg,image/webp">
        </label>
        <?php if ($imagem = url_imagem($item['imagem'] ?? null)): ?>
            <div class="linha-inteira"><img src="<?= $imagem ?>" alt="Imagem atual" class="miniatura"> <span class="texto-suave">Imagem atual (envie outra para trocar)</span></div>
        <?php endif; ?>
        <fieldset class="linha-inteira">
            <legend>Receita: ingredientes usados em uma unidade</legend>
            <p class="texto-suave" style="font-size: 0.9em; margin-bottom: 10px;">O estoque é descontado conforme a receita a cada pedido. Deixe 0 nos que não são usados.</p>
            <div class="grade-ingredientes">
                <?php foreach ($ingredientes as $ing): ?>
                    <label><?= e($ing['nome_ingrediente']) ?>
                        <input type="number" name="ingredientes[<?= (int) $ing['id_ingrediente'] ?>]" min="0" max="1000"
                               value="<?= (int) ($receita[(int) $ing['id_ingrediente']] ?? 0) ?>">
                    </label>
                <?php endforeach; ?>
            </div>
        </fieldset>
        <div class="acoes linha-inteira" style="justify-content: flex-start;">
            <button type="submit">Salvar</button>
            <a href="cardapio.php" class="botao botao-secundario">Cancelar</a>
        </div>
    </form>
</div>
<?php
pagina_fim();
