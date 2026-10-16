<?php
require_once __DIR__ . '/../app/bootstrap.php';
redirecionar_se_logado();

pagina_login_inicio('Login');
?>
<div class="login-container">
    <h2>Login</h2>
    <?= exibir_mensagens() ?>
    <form action="valida_login.php" method="post">
        <?= csrf_campo() ?>
        <label for="login">Usuário</label>
        <input type="text" id="login" name="login" autocomplete="username" required>

        <label for="senha">Senha</label>
        <input type="password" id="senha" name="senha" autocomplete="current-password" required>

        <button type="submit">Entrar</button>

        <p>Não tem uma conta? <a href="cadastro.php">Cadastre-se</a></p>
        <p>Só quer ver o cardápio? <a href="cardapio_cliente.php">Ver cardápio</a></p>
        <p>Balconista ou dono? <a href="login_balconista.php">Clique aqui</a></p>
        <p>Cozinheiro? <a href="login_cozinheiro.php">Clique aqui</a></p>
    </form>
    <?php if (env('MOSTRAR_CONTAS_DEMO', '0') === '1'): ?>
        <div class="contas-demo">Conta de demonstração: <strong>joao</strong> / <strong>123456</strong></div>
    <?php endif; ?>
</div>
<?php
pagina_login_fim();
