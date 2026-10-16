<?php
require_once __DIR__ . '/../app/bootstrap.php';
$usuario = exigir_perfil(PERFIL_COZINHEIRO);

$dados = buscar_funcionario(PERFIL_COZINHEIRO, (string) $usuario['id']);
if (!$dados) {
    sair();
    redirecionar(PAGINA_LOGIN[PERFIL_COZINHEIRO]);
}

pagina_inicio('Meu perfil');
?>
<div class="container estreito">
    <h2>Dados do Cozinheiro</h2>
    <table class="dados-perfil">
        <tr><th>CPF</th><td><?= e(formata_cpf($dados['cpf'])) ?></td></tr>
        <tr><th>Nome</th><td><?= e($dados['nome']) ?></td></tr>
        <tr><th>E-mail</th><td><?= e($dados['email']) ?></td></tr>
        <tr><th>Nascimento</th><td><?= e(data_br($dados['data_nasc'])) ?></td></tr>
        <tr><th>Sexo</th><td><?= e($dados['sexo']) ?></td></tr>
    </table>
    <div class="acoes" style="justify-content: flex-start; margin-top: 20px;">
        <a class="botao" href="editar_cozinheiro.php">Editar dados</a>

    </div>
</div>
<?php
pagina_fim();
