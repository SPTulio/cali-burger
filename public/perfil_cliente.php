<?php
require_once __DIR__ . '/../app/bootstrap.php';
$usuario = exigir_perfil(PERFIL_CLIENTE);

$cliente = consultar_um(
    'SELECT login, nome, email, cpf, data_nasc, end_estado, end_cidade, end_bairro, end_logradouro FROM cliente WHERE id = ?',
    [(int) $usuario['id']]
);
if (!$cliente) {
    sair();
    redirecionar('login.php');
}

pagina_inicio('Meu perfil', ['menu' => PERFIL_CLIENTE]);
?>
<div class="container estreito">
    <h2>Meus dados</h2>
    <table class="dados-perfil">
        <tr><th>Usuário</th><td><?= e($cliente['login']) ?></td></tr>
        <tr><th>Nome</th><td><?= e($cliente['nome']) ?></td></tr>
        <tr><th>E-mail</th><td><?= e($cliente['email']) ?></td></tr>
        <tr><th>CPF</th><td><?= e(formata_cpf($cliente['cpf'])) ?></td></tr>
        <tr><th>Nascimento</th><td><?= e(data_br($cliente['data_nasc'])) ?></td></tr>
        <tr><th>Endereço</th><td><?= e($cliente['end_logradouro'] . ', ' . $cliente['end_bairro'] . ', ' . $cliente['end_cidade'] . ' - ' . $cliente['end_estado']) ?></td></tr>
    </table>
    <div class="acoes" style="justify-content: flex-start; margin-top: 20px;">
        <a class="botao" href="editar_cliente.php">Editar dados</a>
        <a class="botao botao-secundario" href="editar_endereco.php">Alterar endereço</a>
        <?= botao_post('deleta_usuario.php', [], 'Excluir conta',
            'Tem certeza que deseja excluir sua conta? Esta ação não pode ser desfeita.', 'botao botao-secundario') ?>
    </div>
</div>
<?php
pagina_fim();
