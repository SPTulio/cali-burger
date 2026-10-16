<?php
// Pedidos para o balconista: aceitar/recusar os novos e acompanhar todos.
require_once __DIR__ . '/../app/bootstrap.php';
exigir_perfil(PERFIL_BALCONISTA);

$visao = campo_get('visao', 'aguardando') === 'todos' ? 'todos' : 'aguardando';
$filtroStatus = campo_get('status_filter');
$filtroData = campo_get('date_filter');

if ($visao === 'aguardando') {
    $pedidos = listar_pedidos(['status' => STATUS_AGUARDANDO]);
} else {
    $filtros = [];
    if (in_array($filtroStatus, TODOS_STATUS, true)) {
        $filtros['status'] = $filtroStatus;
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $filtroData)) {
        $filtros['desde'] = $filtroData;
    }
    $pedidos = listar_pedidos($filtros);
}
$voltar = 'pedidos.php?' . http_build_query(array_filter(['visao' => $visao, 'status_filter' => $filtroStatus, 'date_filter' => $filtroData]));
$aguardando = contar_pedidos_aguardando();

pagina_inicio('Pedidos');
?>
<div class="container largo">
    <h2>Pedidos</h2>
    <div class="abas">
        <a href="pedidos.php?visao=aguardando" class="<?= $visao === 'aguardando' ? 'ativa' : '' ?>">Aguardando aceitação (<?= $aguardando ?>)</a>
        <a href="pedidos.php?visao=todos" class="<?= $visao === 'todos' ? 'ativa' : '' ?>">Todos os pedidos</a>
    </div>

    <?php if ($visao === 'todos'): ?>
        <form method="GET" class="filtros">
            <input type="hidden" name="visao" value="todos">
            <label for="status_filter">Status:</label>
            <select name="status_filter" id="status_filter">
                <option value="">Todos</option>
                <?php foreach (TODOS_STATUS as $status): ?>
                    <option value="<?= e($status) ?>" <?= $filtroStatus === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                <?php endforeach; ?>
            </select>
            <label for="date_filter">A partir de:</label>
            <input type="datetime-local" name="date_filter" id="date_filter" value="<?= e($filtroData) ?>">
            <button type="submit">Filtrar</button>
            <a href="relatorio.php" class="botao botao-secundario">Relatório de vendas</a>
        </form>
    <?php endif; ?>

    <div class="tabela-rolagem">
        <table class="styled-table">
            <thead>
                <tr>
                    <th>Nº</th><th>Itens</th><th>Valor</th><th>Cliente</th>
                    <th>Status</th><th>Data e hora</th><th>Observações</th><th>Ação</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$pedidos): ?>
                <tr><td colspan="8"><?= $visao === 'aguardando' ? 'Nenhum pedido aguardando aceitação.' : 'Nenhum pedido encontrado.' ?></td></tr>
            <?php endif; ?>
            <?php foreach ($pedidos as $p): ?>
                <tr>
                    <td><?= (int) $p['numero_do_pedido'] ?></td>
                    <td class="esquerda"><?= html_itens_pedido($p) ?></td>
                    <td class="nowrap"><?= dinheiro($p['valor']) ?></td>
                    <td><?= e($p['nome_cliente']) ?></td>
                    <td><?= html_status($p['status']) ?></td>
                    <td><?= data_hora($p['data_pedido']) ?></td>
                    <td class="esquerda"><?= html_observacoes_pedido($p) ?></td>
                    <td>
                        <div class="acoes">
                        <?php if ($p['status'] === STATUS_AGUARDANDO): ?>
                            <?= botao_post('atualizar_status_pedido.php',
                                ['numero_do_pedido' => $p['numero_do_pedido'], 'acao' => 'aceitar', 'voltar' => $voltar],
                                'Aceitar', 'Aceitar o pedido nº ' . $p['numero_do_pedido'] . '?', 'botao botao-sucesso botao-pequeno') ?>
                            <?= botao_post('atualizar_status_pedido.php',
                                ['numero_do_pedido' => $p['numero_do_pedido'], 'acao' => 'recusar', 'voltar' => $voltar],
                                'Recusar', 'Recusar o pedido nº ' . $p['numero_do_pedido'] . '?', 'botao botao-pequeno') ?>
                        <?php elseif ($p['status'] === STATUS_PREPARO): ?>
                            <?= botao_post('atualizar_status_pedido.php',
                                ['numero_do_pedido' => $p['numero_do_pedido'], 'acao' => 'confirmar', 'voltar' => $voltar],
                                'Saiu para entrega', 'Confirmar que o pedido nº ' . $p['numero_do_pedido'] . ' saiu para entrega?', 'botao botao-pequeno') ?>
                        <?php else: ?>
                            <span class="texto-suave">—</span>
                        <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php
pagina_fim();
