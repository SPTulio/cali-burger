<?php
// O cliente edita apenas os próprios dados (o id vem da sessão, nunca do formulário).
require_once __DIR__ . '/../app/bootstrap.php';
$usuario = exigir_perfil(PERFIL_CLIENTE);
$id = (int) $usuario['id'];

$cliente = consultar_um('SELECT login, nome, email, cpf, data_nasc FROM cliente WHERE id = ?', [$id]);
if (!$cliente) {
    redirecionar('perfil_cliente.php');
}
$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cliente = [
        'login' => campo('login'), 'nome' => campo('nome'), 'email' => campo('email'),
        'cpf' => somente_digitos(campo('cpf')), 'data_nasc' => campo('data_nasc'),
    ];
    if (!preg_match('/^[A-Za-z0-9._-]{3,30}$/', $cliente['login'])) {
        $erros[] = 'O usuário deve ter de 3 a 30 caracteres (letras, números, ponto, hífen ou _).';
    }
    if ($cliente['nome'] === '' || mb_strlen($cliente['nome']) > 100) {
        $erros[] = 'Informe seu nome.';
    }
    if (!valida_email($cliente['email'])) {
        $erros[] = 'E-mail inválido.';
    }
    if (!valida_cpf($cliente['cpf'])) {
        $erros[] = 'CPF inválido.';
    }
    if (!valida_data_nascimento($cliente['data_nasc'])) {
        $erros[] = 'Data de nascimento inválida.';
    }
    if (!$erros && consultar_um('SELECT id FROM cliente WHERE (login = ? OR cpf = ?) AND id <> ?', [$cliente['login'], $cliente['cpf'], $id])) {
        $erros[] = 'Usuário ou CPF já usado por outra conta.';
    }
    if (!$erros && ($erroSenha = processar_troca_senha('cliente', 'id', $id))) {
        $erros[] = $erroSenha;
    }
    if (!$erros) {
        alterar(
            'UPDATE cliente SET login = ?, nome = ?, email = ?, cpf = ?, data_nasc = ? WHERE id = ?',
            [$cliente['login'], $cliente['nome'], $cliente['email'], $cliente['cpf'], $cliente['data_nasc'], $id]
        );
        $_SESSION['usuario']['nome'] = $cliente['nome'];
        mensagem('Dados atualizados com sucesso.');
        redirecionar('perfil_cliente.php');
    }
}

pagina_inicio('Editar dados', ['menu' => PERFIL_CLIENTE]);
?>
<div class="container estreito">
    <h2>Editar dados</h2>
    <?php foreach ($erros as $erro): ?>
        <div class="alerta alerta-erro"><?= e($erro) ?></div>
    <?php endforeach; ?>
    <form method="POST" class="formulario duas-colunas">
        <?= csrf_campo() ?>
        <label>Usuário<input type="text" name="login" value="<?= e($cliente['login']) ?>" required maxlength="30"></label>
        <label>Nome<input type="text" name="nome" value="<?= e($cliente['nome']) ?>" required maxlength="100"></label>
        <label>E-mail<input type="email" name="email" value="<?= e($cliente['email']) ?>" required maxlength="255"></label>
        <label>CPF<input type="text" name="cpf" value="<?= e(formata_cpf($cliente['cpf'])) ?>" required inputmode="numeric"></label>
        <label>Nascimento<input type="date" name="data_nasc" value="<?= e($cliente['data_nasc']) ?>" max="<?= date('Y-m-d') ?>" required></label>
        <span></span>
        <?= html_campos_troca_senha() ?>
        <div class="acoes linha-inteira" style="justify-content: flex-start;">
            <button type="submit">Salvar</button>
            <a href="perfil_cliente.php" class="botao botao-secundario">Cancelar</a>
        </div>
    </form>
</div>
<?php
pagina_fim();
