<?php
// Relatório de vendas por período: produtos vendidos, ingredientes usados
// e ingredientes a comprar. Pedidos cancelados/recusados não entram.
require_once __DIR__ . '/../app/bootstrap.php';
exigir_perfil(PERFIL_BALCONISTA);

$inicio = campo_get('start_date');
$fim = campo_get('end_date');
$valida = fn($d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) === 1;

$where = ['p.status <> ?'];
$parametros = [STATUS_CANCELADO];
if ($valida($inicio)) {
    $where[] = 'p.data_pedido >= ?';
    $parametros[] = $inicio . ' 00:00:00';
}
if ($valida($fim)) {
    // "Até" inclui o dia inteiro (antes, os pedidos do último dia ficavam de fora).
    $where[] = 'p.data_pedido < DATE_ADD(?, INTERVAL 1 DAY)';
    $parametros[] = $fim;
}
$condicao = implode(' AND ', $where);

$vendas = consultar(
    "SELECT DATE(p.data_pedido) AS data_venda, ip.produto,
            SUM(ip.quantidade) AS quantidade_vendida, SUM(ip.valor) AS valor_total
       FROM itens_pedido ip
       JOIN pedido p ON p.numero_do_pedido = ip.numero_do_pedido
      WHERE $condicao
      GROUP BY DATE(p.data_pedido), ip.produto
      ORDER BY data_venda DESC, ip.produto",
    $parametros
);
$resumo = consultar_um(
    "SELECT COUNT(*) AS pedidos, COALESCE(SUM(p.valor), 0) AS faturamento FROM pedido p WHERE $condicao",
    $parametros
);
// Consumo real registrado na hora do pedido (antes era estimado pelo nome do produto).
$ingredientesUsados = consultar(
    "SELECT e.nome_ingrediente AS nome, SUM(pe.quantidade) AS total_usado
       FROM pedido_estoque pe
       JOIN pedido p ON p.numero_do_pedido = pe.numero_do_pedido
       JOIN estoque e ON e.id_ingrediente = pe.id_ingrediente
      WHERE $condicao
      GROUP BY e.id_ingrediente, e.nome_ingrediente
      ORDER BY total_usado DESC",
    $parametros
);
$aComprar = consultar('SELECT nome_ingrediente AS nome, quantidade FROM estoque WHERE quantidade <= ? ORDER BY quantidade', [ESTOQUE_BAIXO]);

$totalItens = array_sum(array_column($vendas, 'quantidade_vendida'));
$ticketMedio = (int) $resumo['pedidos'] > 0 ? (float) $resumo['faturamento'] / (int) $resumo['pedidos'] : 0;

pagina_inicio('Relatório de vendas', ['estilo' => '
    .indicadores { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin: 25px 0; }
    .indicador { border: 1px solid #eee; border-radius: 12px; padding: 16px; }
    .indicador strong { display: block; font-size: 1.6em; color: #c0392b; }
    .duas-tabelas { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
    @media (max-width: 800px) { .duas-tabelas { grid-template-columns: 1fr; } }
']);
?>
<div class="container largo">
    <h2>Relatório de vendas</h2>
    <form method="GET" class="filtros">
        <label for="start_date">De:</label>
        <input type="date" id="start_date" name="start_date" value="<?= e($inicio) ?>">
        <label for="end_date">Até:</label>
        <input type="date" id="end_date" name="end_date" value="<?= e($fim) ?>">
        <button type="submit">Filtrar</button>
        <?php if ($inicio || $fim): ?><a href="relatorio.php" class="botao botao-secundario">Limpar</a><?php endif; ?>
    </form>

    <div class="indicadores">
        <div class="indicador"><span>Faturamento</span><strong><?= dinheiro($resumo['faturamento']) ?></strong></div>
        <div class="indicador"><span>Pedidos</span><strong><?= (int) $resumo['pedidos'] ?></strong></div>
        <div class="indicador"><span>Itens vendidos</span><strong><?= (int) $totalItens ?></strong></div>
        <div class="indicador"><span>Ticket médio</span><strong><?= dinheiro($ticketMedio) ?></strong></div>
    </div>
    <p class="texto-suave" style="font-size: 0.9em;">Pedidos cancelados ou recusados não entram no relatório.</p>

    <h3>Vendas por dia e produto</h3>
    <div class="tabela-rolagem">
        <table class="styled-table">
            <thead><tr><th>Data</th><th>Produto</th><th>Quantidade</th><th>Valor unitário</th><th>Total</th></tr></thead>
            <tbody>
            <?php if (!$vendas): ?>
                <tr><td colspan="5">Nenhuma venda no período selecionado.</td></tr>
            <?php endif; ?>
            <?php foreach ($vendas as $v): ?>
                <tr>
                    <td><?= e(data_br($v['data_venda'])) ?></td>
                    <td class="esquerda"><?= e($v['produto']) ?></td>
                    <td><?= (int) $v['quantidade_vendida'] ?></td>
                    <td><?= dinheiro((float) $v['valor_total'] / max(1, (int) $v['quantidade_vendida'])) ?></td>
                    <td><?= dinheiro($v['valor_total']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="duas-tabelas">
        <div>
            <h3>Ingredientes usados</h3>
            <table class="styled-table">
                <thead><tr><th class="esquerda">Ingrediente</th><th>Quantidade usada</th></tr></thead>
                <tbody>
                <?php if (!$ingredientesUsados): ?>
                    <tr><td colspan="2">Nenhum ingrediente usado no período.</td></tr>
                <?php endif; ?>
                <?php foreach ($ingredientesUsados as $i): ?>
                    <tr><td class="esquerda"><?= e($i['nome']) ?></td><td><?= (int) $i['total_usado'] ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div>
            <h3>Ingredientes a comprar</h3>
            <table class="styled-table">
                <thead><tr><th class="esquerda">Ingrediente</th><th>Em estoque</th></tr></thead>
                <tbody>
                <?php if (!$aComprar): ?>
                    <tr><td colspan="2">Nenhum ingrediente com estoque baixo.</td></tr>
                <?php endif; ?>
                <?php foreach ($aComprar as $i): ?>
                    <tr><td class="esquerda"><?= e($i['nome']) ?></td><td><?= (int) $i['quantidade'] ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php
pagina_fim();
