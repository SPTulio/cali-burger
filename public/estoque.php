<?php
// Estoque de ingredientes.
require_once __DIR__ . '/../app/bootstrap.php';
exigir_perfil(PERFIL_BALCONISTA);

$ingredientes = consultar(
    'SELECT e.id_ingrediente, e.nome_ingrediente, e.quantidade, COUNT(ci.id_cardapio) AS usado_em
       FROM estoque e
       LEFT JOIN cardapio_ingrediente ci ON ci.id_ingrediente = e.id_ingrediente
      GROUP BY e.id_ingrediente, e.nome_ingrediente, e.quantidade
      ORDER BY e.nome_ingrediente'
);
$baixos = array_filter($ingredientes, fn($i) => (int) $i['quantidade'] <= ESTOQUE_BAIXO);

pagina_inicio('Estoque');
?>
<div class="container largo">
    <h2>Ingredientes em estoque</h2>
    <?php if ($baixos): ?>
        <p><?= count($baixos) ?> ingrediente(s) com estoque baixo (<?= ESTOQUE_BAIXO ?> unidades ou menos).</p>
    <?php endif; ?>

    <form action="adiciona_ingrediente.php" method="POST" class="form-inline">
        <?= csrf_campo() ?>
        <input type="text" name="nome_ingrediente" placeholder="Ingrediente" required maxlength="50" list="lista-ingredientes">
        <datalist id="lista-ingredientes">
            <?php foreach ($ingredientes as $i): ?><option value="<?= e($i['nome_ingrediente']) ?>"><?php endforeach; ?>
        </datalist>
        <input type="number" name="quantidade" placeholder="Quantidade" required min="1" max="100000">
        <button type="submit">Adicionar ao estoque</button>
    </form>
    <p class="texto-suave" style="font-size: 0.9em;">Se o ingrediente já existir, a quantidade é somada ao estoque atual.</p>

    <div class="tabela-rolagem">
        <table class="styled-table">
            <thead>
                <tr><th>Ingrediente</th><th>Quantidade</th><th>Usado em</th><th>Situação</th><th>Ações</th></tr>
            </thead>
            <tbody>
            <?php if (!$ingredientes): ?>
                <tr><td colspan="5">Nenhum ingrediente cadastrado.</td></tr>
            <?php endif; ?>
            <?php foreach ($ingredientes as $i): ?>
                <tr>
                    <td class="esquerda"><?= e($i['nome_ingrediente']) ?></td>
                    <td><?= (int) $i['quantidade'] ?></td>
                    <td><?= (int) $i['usado_em'] ?> item(ns)</td>
                    <td>
                        <?php if ((int) $i['quantidade'] === 0): ?>
                            <span class="status status-cancelado">Em falta</span>
                        <?php elseif ((int) $i['quantidade'] <= ESTOQUE_BAIXO): ?>
                            <span class="status status-aguardando">Estoque baixo</span>
                        <?php else: ?>
                            <span class="status status-concluido">OK</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="acoes">
                            <a href="editar_ingrediente.php?id=<?= (int) $i['id_ingrediente'] ?>" class="botao botao-pequeno botao-secundario">Editar</a>
                            <?= botao_post('deleta_ingrediente.php', ['id' => $i['id_ingrediente']], 'Excluir',
                                'Excluir "' . $i['nome_ingrediente'] . '"?' . ((int) $i['usado_em'] > 0 ? ' Ele será retirado das receitas de ' . (int) $i['usado_em'] . ' item(ns).' : ''),
                                'botao botao-pequeno') ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php
pagina_fim();
