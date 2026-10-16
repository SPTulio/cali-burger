<?php
require_once __DIR__ . '/../app/bootstrap.php';
exigir_perfil(PERFIL_BALCONISTA);

$id = campo_inteiro('id', $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET) ?? 0;
$ingrediente = consultar_um('SELECT id_ingrediente, nome_ingrediente, quantidade FROM estoque WHERE id_ingrediente = ?', [$id]);
if (!$ingrediente) {
    mensagem('Ingrediente não encontrado.', 'erro');
    redirecionar('estoque.php');
}
$erro = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = campo('nome_ingrediente');
    $quantidade = campo_inteiro('quantidade');
    if ($nome === '' || mb_strlen($nome) > 50) {
        $erro = 'Informe o nome do ingrediente (até 50 caracteres).';
    } elseif ($quantidade === null || $quantidade < 0 || $quantidade > 100000) {
        $erro = 'A quantidade deve ser um número entre 0 e 100.000.';
    } elseif (consultar_um('SELECT id_ingrediente FROM estoque WHERE nome_ingrediente = ? AND id_ingrediente <> ?', [$nome, $id])) {
        $erro = 'Já existe outro ingrediente com esse nome.';
    } else {
        alterar('UPDATE estoque SET nome_ingrediente = ?, quantidade = ? WHERE id_ingrediente = ?', [$nome, $quantidade, $id]);
        mensagem('Ingrediente atualizado.');
        redirecionar('estoque.php');
    }
    $ingrediente = ['id_ingrediente' => $id, 'nome_ingrediente' => $nome, 'quantidade' => $_POST['quantidade'] ?? ''];
}

pagina_inicio('Editar ingrediente');
?>
<div class="container estreito">
    <h2>Editar ingrediente</h2>
    <?php if ($erro): ?>
        <div class="alerta alerta-erro"><?= e($erro) ?></div>
    <?php endif; ?>
    <form method="POST" class="formulario" data-confirmar="Salvar as alterações deste ingrediente?">
        <?= csrf_campo() ?>
        <input type="hidden" name="id" value="<?= (int) $id ?>">
        <label>Ingrediente<input type="text" name="nome_ingrediente" value="<?= e($ingrediente['nome_ingrediente']) ?>" required maxlength="50"></label>
        <label>Quantidade em estoque<input type="number" name="quantidade" value="<?= e($ingrediente['quantidade']) ?>" required min="0" max="100000"></label>
        <div class="acoes" style="justify-content: flex-start;">
            <button type="submit">Salvar</button>
            <a href="estoque.php" class="botao botao-secundario">Cancelar</a>
        </div>
    </form>
</div>
<?php
pagina_fim();
