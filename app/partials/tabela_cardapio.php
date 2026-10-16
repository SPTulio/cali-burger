<?php
// Tabela de itens do cardápio usada pelo balconista e pelo cozinheiro.
// Espera $itens (de listar_cardapio) e, opcionalmente, $somenteLeitura.
$somenteLeitura ??= false;
?>
<div class="tabela-rolagem">
    <table class="styled-table">
        <thead>
            <tr>
                <th>Imagem</th><th>Nome</th><th>Categoria</th><th>Descrição</th>
                <th>Ingredientes</th><th>Preço</th><th>Disponibilidade</th>
                <?php if (!$somenteLeitura): ?><th>Ações</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
        <?php if (!$itens): ?>
            <tr><td colspan="8">Nenhum item cadastrado.</td></tr>
        <?php endif; ?>
        <?php foreach ($itens as $item): ?>
            <tr>
                <td>
                    <?php if ($imagem = url_imagem($item['imagem'])): ?>
                        <img src="<?= $imagem ?>" alt="<?= e($item['nome']) ?>" class="miniatura" loading="lazy">
                    <?php else: ?>
                        <span class="texto-suave">Sem imagem</span>
                    <?php endif; ?>
                </td>
                <td><strong><?= e($item['nome']) ?></strong></td>
                <td><?= e($item['categoria_nome']) ?></td>
                <td class="esquerda"><?= e($item['descricao']) ?></td>
                <td class="esquerda"><?= $item['ingredientes'] ? e($item['ingredientes']) : '<span class="texto-suave">—</span>' ?></td>
                <td><?= dinheiro($item['preco']) ?></td>
                <td><?= html_disponibilidade($item) ?></td>
                <?php if (!$somenteLeitura): ?>
                    <td>
                        <div class="acoes">
                            <a href="editar_cardapio.php?id=<?= (int) $item['id'] ?>" class="botao botao-pequeno botao-secundario">Editar</a>
                            <?= botao_post('cardapio.php', ['action' => 'delete', 'id' => $item['id']], 'Excluir',
                                'Excluir "' . $item['nome'] . '" do cardápio?', 'botao botao-pequeno') ?>
                        </div>
                    </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
