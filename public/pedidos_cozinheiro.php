<?php
// Pedidos em preparo para a cozinha. Ao terminar, o cozinheiro envia para entrega.
require_once __DIR__ . '/../app/bootstrap.php';
exigir_perfil(PERFIL_COZINHEIRO);

$pedidos = array_reverse(listar_pedidos(['status' => STATUS_PREPARO])); // mais antigos primeiro

pagina_inicio('Pedidos da cozinha', ['estilo' => '
    .cartoes { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 16px; margin-top: 20px; text-align: left; }
    .cartao { border: 1px solid #eee; border-radius: 12px; padding: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); display: flex; flex-direction: column; gap: 10px; }
    .cartao h3 { margin: 0 !important; display: flex; justify-content: space-between; }
    .cartao .lista-itens li { font-size: 1.1em; padding: 2px 0; }
    .cartao .obs { background: #fff4d6; border-radius: 8px; padding: 8px; font-size: 0.9em; }
    .cartao form, .cartao button { width: 100%; }
']);
?>
<div class="container largo">
    <h2>Pedidos para preparar</h2>
    <p><?= count($pedidos) ?> pedido(s) em preparo. A página atualiza sozinha a cada 30 segundos.</p>
    <?php if (!$pedidos): ?>
        <p class="texto-suave" style="margin-top: 30px;">Nenhum pedido na fila. 🍔</p>
    <?php endif; ?>
    <div class="cartoes">
        <?php foreach ($pedidos as $p): ?>
            <div class="cartao">
                <h3><span>Pedido nº <?= (int) $p['numero_do_pedido'] ?></span><span class="texto-suave"><?= e(date('H:i', strtotime($p['data_pedido']))) ?></span></h3>
                <div class="texto-suave"><?= e($p['nome_cliente']) ?></div>
                <?= html_itens_pedido($p) ?>
                <?php $obs = array_filter($p['itens'], fn($i) => !empty($i['observacao'])); ?>
                <?php if ($obs): ?>
                    <div class="obs"><?= html_observacoes_pedido($p) ?></div>
                <?php endif; ?>
                <?= botao_post('atualizar_status_pedido.php',
                    ['numero_do_pedido' => $p['numero_do_pedido'], 'acao' => 'confirmar', 'voltar' => 'pedidos_cozinheiro.php'],
                    'Pronto: enviar para entrega', 'Confirmar que o pedido nº ' . $p['numero_do_pedido'] . ' está pronto para entrega?') ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<script>
    // Atualiza a lista periodicamente, a menos que a janela de confirmação esteja aberta.
    setInterval(function () {
        if (document.getElementById('modal-confirmacao').hidden) {
            location.reload();
        }
    }, 30000);
</script>
<?php
pagina_fim();
