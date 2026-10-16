<?php
// O funcionário edita apenas os próprios dados (o CPF vem da sessão).
require_once __DIR__ . '/../app/bootstrap.php';
$usuario = exigir_perfil(PERFIL_BALCONISTA);
$cpf = (string) $usuario['id'];

$dados = buscar_funcionario(PERFIL_BALCONISTA, $cpf);
$erros = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $erros = atualizar_funcionario(PERFIL_BALCONISTA, $cpf);
    if (!$erros) {
        mensagem('Dados atualizados com sucesso.');
        redirecionar('perfil_balconista_dono.php');
    }
    $dados = dados_funcionario_do_formulario();
}

pagina_inicio('Editar dados');
?>
<div class="container estreito">
    <h2>Editar dados</h2>
    <p class="texto-suave">CPF: <?= e(formata_cpf($cpf)) ?></p>
    <?php foreach ($erros as $erro): ?>
        <div class="alerta alerta-erro"><?= e($erro) ?></div>
    <?php endforeach; ?>
    <form method="POST" class="formulario duas-colunas" style="margin-top: 15px;">
        <?= csrf_campo() ?>
        <?= html_campos_funcionario($dados ?? [], false) ?>
        <?= html_campos_troca_senha() ?>
        <div class="acoes linha-inteira" style="justify-content: flex-start;">
            <button type="submit">Salvar</button>
            <a href="perfil_balconista_dono.php" class="botao botao-secundario">Cancelar</a>
        </div>
    </form>
</div>
<?php
pagina_fim();
