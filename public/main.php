<?php
// Equipe: cadastro e exclusão de balconistas/donos e cozinheiros.
require_once __DIR__ . '/../app/bootstrap.php';
$usuario = exigir_perfil(PERFIL_BALCONISTA);

$erros = [PERFIL_BALCONISTA => [], PERFIL_COZINHEIRO => []];
$enviado = [PERFIL_BALCONISTA => [], PERFIL_COZINHEIRO => []];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $perfil = campo('form_type') === PERFIL_COZINHEIRO ? PERFIL_COZINHEIRO : PERFIL_BALCONISTA;
    $erros[$perfil] = cadastrar_funcionario($perfil);
    if (!$erros[$perfil]) {
        mensagem(($perfil === PERFIL_COZINHEIRO ? 'Cozinheiro' : 'Balconista') . ' cadastrado com sucesso!');
        redirecionar('main.php');
    }
    $enviado[$perfil] = dados_funcionario_do_formulario();
}

$equipes = [
    PERFIL_BALCONISTA => ['titulo' => 'Balconistas e donos', 'acao' => 'delete_balconista_dono.php', 'botao' => 'Cadastrar balconista'],
    PERFIL_COZINHEIRO => ['titulo' => 'Cozinheiros', 'acao' => 'delete_cozinheiro.php', 'botao' => 'Cadastrar cozinheiro'],
];

pagina_inicio('Equipe');
?>
<div class="container largo">
    <h2>Equipe</h2>
    <p>Bem-vindo, <?= e($usuario['nome']) ?>! Cadastre e gerencie quem trabalha na lanchonete.</p>

    <?php foreach ($equipes as $perfil => $equipe): ?>
        <h3><?= e($equipe['titulo']) ?></h3>
        <div class="tabela-rolagem">
            <table class="styled-table">
                <thead>
                    <tr><th>CPF</th><th>Nome</th><th>Nascimento</th><th>E-mail</th><th>Sexo</th><th>Ação</th></tr>
                </thead>
                <tbody>
                <?php foreach (listar_funcionarios($perfil) as $f): ?>
                    <tr>
                        <td><?= e(formata_cpf($f['cpf'])) ?></td>
                        <td><?= e($f['nome']) ?></td>
                        <td><?= e(data_br($f['data_nasc'])) ?></td>
                        <td><?= e($f['email']) ?></td>
                        <td><?= e($f['sexo']) ?></td>
                        <td>
                            <?php if ($perfil === PERFIL_BALCONISTA && $f['cpf'] === $usuario['id']): ?>
                                <span class="texto-suave">Você</span>
                            <?php else: ?>
                                <?= botao_post($equipe['acao'], ['cpf' => $f['cpf']], 'Excluir',
                                    'Deseja excluir ' . $f['nome'] . '?', 'botao botao-pequeno') ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <details <?= $erros[$perfil] ? 'open' : '' ?> style="margin-top: 15px; text-align: left;">
            <summary class="botao botao-secundario" style="display: inline-block;"><?= e($equipe['botao']) ?></summary>
            <?php foreach ($erros[$perfil] as $erro): ?>
                <div class="alerta alerta-erro" style="margin-top: 10px;"><?= e($erro) ?></div>
            <?php endforeach; ?>
            <form method="POST" class="formulario duas-colunas" style="margin-top: 15px;">
                <?= csrf_campo() ?>
                <input type="hidden" name="form_type" value="<?= e($perfil) ?>">
                <?= html_campos_funcionario($enviado[$perfil], true) ?>
                <label>Senha<input type="password" name="senha" minlength="6" autocomplete="new-password" required></label>
                <label>Confirmar senha<input type="password" name="confirmar_senha" minlength="6" autocomplete="new-password" required></label>
                <div class="linha-inteira"><button type="submit"><?= e($equipe['botao']) ?></button></div>
            </form>
        </details>
    <?php endforeach; ?>
</div>
<?php
pagina_fim();
