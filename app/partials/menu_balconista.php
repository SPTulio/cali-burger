<?php $usuario = usuario_logado(); $aguardando = $usuario ? contar_pedidos_aguardando() : 0; ?>
<header>
    <div class="logo-area">
        <img src="imagens/logo_cali_sem_fundo.png" alt="Logo Cali Burger">
        <h1>Cali Burger</h1>
    </div>
</header>
<nav>
    <a href="pedidos.php">Pedidos<?php if ($aguardando > 0): ?> <span class="contador" title="Aguardando aceitação"><?= $aguardando ?></span><?php endif; ?></a>
    <a href="cardapio.php">Cardápio</a>
    <a href="estoque.php">Estoque</a>
    <a href="relatorio.php">Relatório</a>
    <a href="main.php">Equipe</a>
    <?php if ($usuario): ?>
        <a href="perfil_balconista_dono.php" class="nav-usuario">
            <img src="imagens/user_icon.png" alt="">
            Olá, <?= e(explode(' ', trim($usuario['nome']))[0]) ?>
        </a>
        <a href="sair.php" class="logout">Sair</a>
    <?php endif; ?>
</nav>
