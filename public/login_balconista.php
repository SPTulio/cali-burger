<?php
require_once __DIR__ . '/../app/bootstrap.php';
redirecionar_se_logado();

pagina_login_inicio('Login Balconista / Dono');
?>
<div class="login-container">
    <h2>Login Balconista / Dono</h2>
    <?= exibir_mensagens() ?>
    <form action="valida_login_balconista.php" method="post">
        <?= csrf_campo() ?>
        <label for="cpf">CPF</label>
        <input type="text" id="cpf" name="cpf" inputmode="numeric" placeholder="000.000.000-00" autocomplete="username" required>

        <label for="senha">Senha</label>
        <input type="password" id="senha" name="senha" autocomplete="current-password" required>

        <button type="submit">Entrar</button>

        <p>Cliente? <a href="login.php">Clique aqui</a></p>
        <p>Cozinheiro? <a href="login_cozinheiro.php">Clique aqui</a></p>
    </form>
    <?php if (env('MOSTRAR_CONTAS_DEMO', '0') === '1'): ?>
        <div class="contas-demo">Conta de demonstração: CPF <strong>352.658.110-01</strong> / senha <strong>123456</strong></div>
    <?php endif; ?>
</div>
<?php
pagina_login_fim();
