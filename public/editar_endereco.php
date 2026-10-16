<?php
// Endereço de entrega do cliente logado.
require_once __DIR__ . '/../app/bootstrap.php';
$usuario = exigir_perfil(PERFIL_CLIENTE);
$id = (int) $usuario['id'];
$voltar = campo_get('voltar') === 'pagamento' || campo('voltar') === 'pagamento' ? 'pagamento.php' : 'perfil_cliente.php';

$endereco = consultar_um('SELECT end_estado, end_cidade, end_bairro, end_logradouro FROM cliente WHERE id = ?', [$id]);
$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $endereco = [];
    foreach (['end_logradouro' => 'o logradouro', 'end_bairro' => 'o bairro', 'end_cidade' => 'a cidade', 'end_estado' => 'o estado'] as $c => $nome) {
        $endereco[$c] = campo($c);
        if ($endereco[$c] === '' || mb_strlen($endereco[$c]) > 255) {
            $erros[] = "Informe $nome.";
        }
    }
    if (!$erros) {
        alterar(
            'UPDATE cliente SET end_estado = ?, end_cidade = ?, end_bairro = ?, end_logradouro = ? WHERE id = ?',
            [$endereco['end_estado'], $endereco['end_cidade'], $endereco['end_bairro'], $endereco['end_logradouro'], $id]
        );
        mensagem('Endereço atualizado.');
        redirecionar($voltar);
    }
}

pagina_inicio('Endereço de entrega', ['menu' => PERFIL_CLIENTE]);
?>
<main class="container estreito">
    <h2>Endereço de entrega</h2>
    <?php foreach ($erros as $erro): ?>
        <div class="alerta alerta-erro"><?= e($erro) ?></div>
    <?php endforeach; ?>
    <form method="post" class="formulario">
        <?= csrf_campo() ?>
        <input type="hidden" name="voltar" value="<?= $voltar === 'pagamento.php' ? 'pagamento' : '' ?>">
        <label>Logradouro<input type="text" name="end_logradouro" value="<?= e($endereco['end_logradouro'] ?? '') ?>" placeholder="Rua, número e complemento" required maxlength="255"></label>
        <label>Bairro<input type="text" name="end_bairro" value="<?= e($endereco['end_bairro'] ?? '') ?>" required maxlength="255"></label>
        <label>Cidade<input type="text" name="end_cidade" value="<?= e($endereco['end_cidade'] ?? '') ?>" required maxlength="255"></label>
        <label>Estado<input type="text" name="end_estado" value="<?= e($endereco['end_estado'] ?? '') ?>" required maxlength="255"></label>
        <div class="acoes" style="justify-content: flex-start;">
            <button type="submit" class="botao-sucesso">Salvar</button>
            <a href="<?= e($voltar) ?>" class="botao botao-secundario">Voltar</a>
        </div>
    </form>
</main>
<?php
pagina_fim();
