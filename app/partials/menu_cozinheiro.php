<?php $usuario = usuario_logado(); ?>
<header>
    <div class="logo-area">
        <img src="imagens/logo_cali_sem_fundo.png" alt="Logo Cali Burger">
        <h1>Cali Burger</h1>
    </div>
</header>
<nav>
    <a href="pedidos_cozinheiro.php">Pedidos</a>
    <a href="cardapio_cozinheiro.php">Cardápio</a>
    <?php if ($usuario): ?>
        <a href="perfil_cozinheiro.php" class="nav-usuario">
            <img src="imagens/user_icon.png" alt="">
            Olá, <?= e(explode(' ', trim($usuario['nome']))[0]) ?>
        </a>
        <a href="sair.php" class="logout">Sair</a>
    <?php endif; ?>
</nav>
