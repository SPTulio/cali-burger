<?php
// Revisão do pedido e pagamento (PIX simulado) antes de confirmar.
require_once __DIR__ . '/../app/bootstrap.php';
$usuario = exigir_perfil(PERFIL_CLIENTE);

$carrinho = normalizar_carrinho($_SESSION['carrinho'] ?? []);
if (!$carrinho) {
    redirecionar('carrinho.php');
}

$cliente = consultar_um('SELECT end_estado, end_cidade, end_bairro, end_logradouro FROM cliente WHERE id = ?', [(int) $usuario['id']]);
$endereco = $cliente
    ? $cliente['end_logradouro'] . ', ' . $cliente['end_bairro'] . ', ' . $cliente['end_cidade'] . ' - ' . $cliente['end_estado']
    : 'Endereço não cadastrado';

$ids = array_keys($carrinho);
$itens = [];
$total = 0;
foreach (consultar('SELECT id, nome, preco FROM cardapio WHERE id IN (' . marcadores(count($ids)) . ')', $ids) as $p) {
    $qtd = $carrinho[(int) $p['id']];
    $subtotal = centavos($p['preco']) * $qtd;
    $total += $subtotal;
    $itens[] = ['nome' => $p['nome'], 'quantidade' => $qtd, 'subtotal' => $subtotal / 100];
}

pagina_inicio('Pagamento', ['menu' => PERFIL_CLIENTE, 'estilo' => '
    .pagamento-container { max-width: 620px; text-align: left; }
    .pagamento-container h2 { text-align: center; }
    .secao { margin-top: 25px; }
    .secao h3 { margin: 0 0 10px; font-size: 1.1em; color: #333; border-bottom: 2px solid #333; padding-bottom: 5px; }
    .caixa { padding: 12px 15px; border: 1px solid #ddd; border-radius: 8px; background-color: #f9f9f9; }
    .qr-code { display: block; margin: 10px auto; width: 200px; height: 200px; }
    .aviso-demo { text-align: center; font-size: 0.85em; color: #8a5a00; background: #fff4d6; padding: 6px; border-radius: 6px; }
    .total { text-align: right; font-weight: 700; font-size: 1.15em; margin-top: 10px; }
    .rodape { display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-top: 25px; flex-wrap: wrap; }
']);
?>
<main class="container pagamento-container">
    <h2>Pagamento via PIX</h2>

    <div class="secao">
        <h3>Endereço de entrega</h3>
        <div class="caixa">
            <p><?= e($endereco) ?></p>
            <p style="margin-top: 8px;"><a href="editar_endereco.php?voltar=pagamento">Alterar endereço</a></p>
        </div>
    </div>

    <div class="secao">
        <h3>Resumo do pedido</h3>
        <table class="styled-table">
            <thead><tr><th class="esquerda">Produto</th><th>Qtd.</th><th>Subtotal</th></tr></thead>
            <tbody>
            <?php foreach ($itens as $item): ?>
                <tr>
                    <td class="esquerda"><?= e($item['nome']) ?></td>
                    <td><?= (int) $item['quantidade'] ?></td>
                    <td><?= dinheiro($item['subtotal']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p class="total">Total: <?= dinheiro($total / 100) ?></p>
    </div>

    <div class="secao">
        <h3>PIX</h3>
        <img src="imagens/qrcode_demo.png" alt="QR code PIX de demonstração" class="qr-code">
        <p class="aviso-demo">Projeto acadêmico: o QR code é apenas ilustrativo e nenhum valor é cobrado.</p>
    </div>

    <form method="post" action="pedido_sucesso.php" class="rodape">
        <?= csrf_campo() ?>
        <a href="carrinho.php" class="botao botao-secundario">Voltar ao carrinho</a>
        <button type="submit" name="confirmar_pagamento" value="1" class="botao-sucesso">Confirmar pagamento</button>
    </form>
</main>
<?php
pagina_fim();
