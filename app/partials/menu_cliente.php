<?php
$usuario = usuario_logado();
$itensCarrinho = array_sum($_SESSION['carrinho'] ?? []);
?>
<header>
    <div class="logo-area">
        <img src="imagens/logo_cali_sem_fundo.png" alt="Logo Cali Burger">
        <h1>Cali Burger</h1>
    </div>
</header>
<nav>
    <a href="cardapio_cliente.php">Cardápio</a>
    <a href="carrinho.php">Carrinho<?php if ($itensCarrinho > 0): ?> <span class="contador"><?= (int) $itensCarrinho ?></span><?php endif; ?></a>
    <?php if ($usuario && $usuario['perfil'] === PERFIL_CLIENTE): ?>
        <a href="meus_pedidos.php">Meus pedidos</a>
        <a href="perfil_cliente.php" class="nav-usuario">
            <img src="imagens/user_icon.png" alt="">
            Olá, <?= e(explode(' ', trim($usuario['nome']))[0]) ?>
        </a>
        <a href="sair.php" class="logout">Sair</a>
    <?php else: ?>
        <a href="login.php" class="logout">Entrar</a>
    <?php endif; ?>
</nav>
