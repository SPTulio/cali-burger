<?php
require_once __DIR__ . '/../app/bootstrap.php';
$usuario = exigir_perfil(PERFIL_BALCONISTA);

$dados = buscar_funcionario(PERFIL_BALCONISTA, (string) $usuario['id']);
if (!$dados) {
    sair();
    redirecionar(PAGINA_LOGIN[PERFIL_BALCONISTA]);
}

pagina_inicio('Meu perfil');
?>
<div class="container estreito">
    <h2>Dados do Balconista</h2>
    <table class="dados-perfil">
        <tr><th>CPF</th><td><?= e(formata_cpf($dados['cpf'])) ?></td></tr>
        <tr><th>Nome</th><td><?= e($dados['nome']) ?></td></tr>
        <tr><th>E-mail</th><td><?= e($dados['email']) ?></td></tr>
        <tr><th>Nascimento</th><td><?= e(data_br($dados['data_nasc'])) ?></td></tr>
        <tr><th>Sexo</th><td><?= e($dados['sexo']) ?></td></tr>
    </table>
    <div class="acoes" style="justify-content: flex-start; margin-top: 20px;">
        <a class="botao" href="editar_balconista_dono.php">Editar dados</a>
        <?= botao_post('delete_balconista_dono.php', ['cpf' => $dados['cpf']], 'Excluir minha conta',
            'Tem certeza que deseja excluir sua conta? Esta ação não pode ser desfeita.', 'botao botao-secundario') ?>
    </div>
</div>
<?php
pagina_fim();
