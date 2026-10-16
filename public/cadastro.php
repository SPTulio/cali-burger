<?php
// Cadastro de novos clientes.
require_once __DIR__ . '/../app/bootstrap.php';
redirecionar_se_logado();

$campos = ['login', 'nome', 'email', 'cpf', 'data_nascimento', 'estado', 'cidade', 'bairro', 'logradouro'];
$dados = array_fill_keys($campos, '');
$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($campos as $c) {
        $dados[$c] = campo($c);
    }
    $senha = (string) ($_POST['senha'] ?? '');
    $cpf = somente_digitos($dados['cpf']);

    if (!preg_match('/^[A-Za-z0-9._-]{3,30}$/', $dados['login'])) {
        $erros[] = 'O usuário deve ter de 3 a 30 caracteres (letras, números, ponto, hífen ou _).';
    }
    if (!valida_senha($senha)) {
        $erros[] = 'A senha deve ter pelo menos 6 caracteres.';
    } elseif ($senha !== ($_POST['confirmar_senha'] ?? '')) {
        $erros[] = 'As senhas não coincidem.';
    }
    if ($dados['nome'] === '' || mb_strlen($dados['nome']) > 100) {
        $erros[] = 'Informe seu nome.';
    }
    if (!valida_email($dados['email'])) {
        $erros[] = 'E-mail inválido.';
    }
    if (!valida_cpf($cpf)) {
        $erros[] = 'CPF inválido.';
    }
    if (!valida_data_nascimento($dados['data_nascimento'])) {
        $erros[] = 'Data de nascimento inválida.';
    }
    foreach (['estado' => 'o estado', 'cidade' => 'a cidade', 'bairro' => 'o bairro', 'logradouro' => 'o logradouro'] as $c => $nome) {
        if ($dados[$c] === '' || mb_strlen($dados[$c]) > 255) {
            $erros[] = "Informe $nome.";
        }
    }

    if (!$erros && consultar_um('SELECT id FROM cliente WHERE login = ? OR cpf = ?', [$dados['login'], $cpf])) {
        $erros[] = 'Usuário ou CPF já cadastrado.';
    }

    if (!$erros) {
        alterar(
            'INSERT INTO cliente (login, senha, nome, email, cpf, data_nasc, end_estado, end_cidade, end_bairro, end_logradouro)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $dados['login'], password_hash($senha, PASSWORD_DEFAULT), $dados['nome'], $dados['email'], $cpf,
                $dados['data_nascimento'], $dados['estado'], $dados['cidade'], $dados['bairro'], $dados['logradouro'],
            ]
        );
        mensagem('Cadastro realizado com sucesso! Faça login para continuar.');
        redirecionar('login.php');
    }
}

pagina_login_inicio('Cadastro');
?>
<div class="login-container cadastro-container">
    <h2>Cadastro</h2>
    <?php foreach ($erros as $erro): ?>
        <div class="alerta alerta-erro"><?= e($erro) ?></div>
    <?php endforeach; ?>
    <form method="post" action="cadastro.php" class="form-grid">
        <?= csrf_campo() ?>
        <div class="form-group">
            <label for="login">Usuário</label>
            <input type="text" name="login" id="login" value="<?= e($dados['login']) ?>" autocomplete="username" required maxlength="30">
        </div>
        <div class="form-group">
            <label for="nome">Nome</label>
            <input type="text" name="nome" id="nome" value="<?= e($dados['nome']) ?>" autocomplete="name" required maxlength="100">
        </div>
        <div class="form-group">
            <label for="senha">Senha</label>
            <input type="password" name="senha" id="senha" autocomplete="new-password" required minlength="6">
        </div>
        <div class="form-group">
            <label for="confirmar_senha">Confirmar senha</label>
            <input type="password" name="confirmar_senha" id="confirmar_senha" autocomplete="new-password" required minlength="6">
        </div>
        <div class="form-group">
            <label for="email">E-mail</label>
            <input type="email" name="email" id="email" value="<?= e($dados['email']) ?>" autocomplete="email" required>
        </div>
        <div class="form-group">
            <label for="cpf">CPF</label>
            <input type="text" name="cpf" id="cpf" value="<?= e($dados['cpf']) ?>" inputmode="numeric" placeholder="000.000.000-00" required>
        </div>
        <div class="form-group">
            <label for="data_nascimento">Nascimento</label>
            <input type="date" name="data_nascimento" id="data_nascimento" value="<?= e($dados['data_nascimento']) ?>" max="<?= date('Y-m-d') ?>" required>
        </div>
        <div class="form-group">
            <label for="estado">Estado</label>
            <input type="text" name="estado" id="estado" value="<?= e($dados['estado']) ?>" required maxlength="255">
        </div>
        <div class="form-group">
            <label for="cidade">Cidade</label>
            <input type="text" name="cidade" id="cidade" value="<?= e($dados['cidade']) ?>" required maxlength="255">
        </div>
        <div class="form-group">
            <label for="bairro">Bairro</label>
            <input type="text" name="bairro" id="bairro" value="<?= e($dados['bairro']) ?>" required maxlength="255">
        </div>
        <div class="form-group" style="grid-column: 1 / -1;">
            <label for="logradouro">Logradouro</label>
            <input type="text" name="logradouro" id="logradouro" value="<?= e($dados['logradouro']) ?>" placeholder="Rua, número e complemento" required maxlength="255">
        </div>
        <div class="form-buttons">
            <button type="submit">Cadastrar</button>
            <p>Já tem uma conta? <a href="login.php">Voltar para o login</a></p>
        </div>
    </form>
</div>
<?php
pagina_login_fim();
