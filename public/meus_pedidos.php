<?php
// Pedidos do cliente logado, com filtros e ações (cancelar / recebi).
require_once __DIR__ . '/../app/bootstrap.php';
$usuario = exigir_perfil(PERFIL_CLIENTE);

$filtroStatus = campo_get('status_filter');
$filtroData = campo_get('date_filter');
$filtros = ['id_cliente' => (int) $usuario['id']];
if (in_array($filtroStatus, TODOS_STATUS, true)) {
    $filtros['status'] = $filtroStatus;
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $filtroData)) {
    $filtros['desde'] = $filtroData . ' 00:00:00';
}
$pedidos = listar_pedidos($filtros);

pagina_inicio('Meus pedidos', ['menu' => PERFIL_CLIENTE]);
?>
<div class="container largo">
    <h2>Meus pedidos</h2>
    <form method="GET" class="filtros">
        <label for="status_filter">Status:</label>
        <select name="status_filter" id="status_filter">
            <option value="">Todos</option>
            <?php foreach (TODOS_STATUS as $status): ?>
                <option value="<?= e($status) ?>" <?= $filtroStatus === $status ? 'selected' : '' ?>><?= e($status) ?></option>
            <?php endforeach; ?>
        </select>
        <label for="date_filter">A partir de:</label>
        <input type="date" name="date_filter" id="date_filter" value="<?= e($filtroData) ?>">
        <button type="submit">Filtrar</button>
    </form>

    <div class="tabela-rolagem">
        <table class="styled-table">
            <thead>
                <tr>
                    <th>Nº</th>
                    <th>Itens</th>
                    <th>Valor</th>
                    <th>Observações</th>
                    <th>Status</th>
                    <th>Data e hora</th>
                    <th>Ação</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$pedidos): ?>
                <tr><td colspan="7">Nenhum pedido encontrado. <a href="cardapio_cliente.php">Que tal pedir agora?</a></td></tr>
            <?php endif; ?>
            <?php foreach ($pedidos as $p): ?>
                <tr>
                    <td><?= (int) $p['numero_do_pedido'] ?></td>
                    <td class="esquerda"><?= html_itens_pedido($p) ?></td>
                    <td class="nowrap"><?= dinheiro($p['valor']) ?></td>
                    <td class="esquerda"><?= html_observacoes_pedido($p) ?></td>
                    <td><?= html_status($p['status']) ?></td>
                    <td><?= data_hora($p['data_pedido']) ?></td>
                    <td>
                        <?php if ($p['status'] === STATUS_AGUARDANDO): ?>
                            <?= botao_post('atualizar_status_pedido.php',
                                ['numero_do_pedido' => $p['numero_do_pedido'], 'acao' => 'cancelar', 'voltar' => 'meus_pedidos.php'],
                                'Cancelar', 'Tem certeza que deseja cancelar este pedido?', 'botao botao-pequeno') ?>
                        <?php elseif ($p['status'] === STATUS_A_CAMINHO): ?>
                            <?= botao_post('atualizar_status_pedido.php',
                                ['numero_do_pedido' => $p['numero_do_pedido'], 'acao' => 'concluir', 'voltar' => 'meus_pedidos.php'],
                                'Recebi meu pedido', 'Confirma que recebeu seu pedido?', 'botao botao-sucesso botao-pequeno') ?>
                        <?php elseif ($p['status'] === STATUS_PREPARO): ?>
                            <span class="texto-suave">Na cozinha</span>
                        <?php else: ?>
                            <span class="texto-suave">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php
pagina_fim();
