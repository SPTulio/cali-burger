<?php
// Regras de negócio dos pedidos: criação (com baixa de estoque em
// transação), mudanças de status permitidas para cada perfil e consultas.

declare(strict_types=1);

const STATUS_AGUARDANDO = 'Aguardando aceitação';
const STATUS_PREPARO = 'Em preparação';
const STATUS_A_CAMINHO = 'A caminho';
const STATUS_CONCLUIDO = 'Concluído';
const STATUS_CANCELADO = 'Cancelado/recusado';

const TODOS_STATUS = [STATUS_AGUARDANDO, STATUS_PREPARO, STATUS_A_CAMINHO, STATUS_CONCLUIDO, STATUS_CANCELADO];

/**
 * Ações sobre um pedido: quem pode executar, a partir de quais status e
 * qual o novo status. Qualquer outra combinação é recusada pelo servidor.
 */
const ACOES_PEDIDO = [
    'aceitar' => ['perfis' => [PERFIL_BALCONISTA], 'de' => [STATUS_AGUARDANDO], 'para' => STATUS_PREPARO,
        'mensagem' => 'Pedido nº %d aceito e enviado para a cozinha.'],
    'recusar' => ['perfis' => [PERFIL_BALCONISTA], 'de' => [STATUS_AGUARDANDO], 'para' => STATUS_CANCELADO,
        'mensagem' => 'Pedido nº %d recusado. Os ingredientes voltaram para o estoque.'],
    'confirmar' => ['perfis' => [PERFIL_COZINHEIRO, PERFIL_BALCONISTA], 'de' => [STATUS_PREPARO], 'para' => STATUS_A_CAMINHO,
        'mensagem' => 'Pedido nº %d saiu para entrega.'],
    'cancelar' => ['perfis' => [PERFIL_CLIENTE], 'de' => [STATUS_AGUARDANDO], 'para' => STATUS_CANCELADO,
        'mensagem' => 'Pedido nº %d cancelado.'],
    'concluir' => ['perfis' => [PERFIL_CLIENTE], 'de' => [STATUS_A_CAMINHO], 'para' => STATUS_CONCLUIDO,
        'mensagem' => 'Obrigado! Pedido nº %d marcado como recebido.'],
];

final class EstoqueInsuficiente extends RuntimeException
{
}

/**
 * Cria o pedido a partir do carrinho. Tudo acontece em uma transação:
 * confere o estoque (com as linhas travadas), grava pedido, itens e
 * pagamento e dá baixa nos ingredientes. Se faltar algo, nada é gravado.
 *
 * @param array $carrinho     [id do item do cardápio => quantidade]
 * @param array $observacoes  [id do item do cardápio => observação]
 * @return int número do pedido
 */
function criar_pedido(array $cliente, array $carrinho, array $observacoes): int
{
    $carrinho = normalizar_carrinho($carrinho);
    if (!$carrinho) {
        throw new InvalidArgumentException('Seu carrinho está vazio.');
    }
    $ids = array_keys($carrinho);

    $produtos = [];
    foreach (consultar('SELECT id, nome, preco FROM cardapio WHERE id IN (' . marcadores(count($ids)) . ')', $ids) as $p) {
        $produtos[(int) $p['id']] = $p;
    }
    if (count($produtos) !== count($ids)) {
        throw new InvalidArgumentException('Um dos itens do carrinho não está mais no cardápio. Revise o carrinho.');
    }

    // Quanto de cada ingrediente o pedido inteiro consome.
    $necessario = [];
    $usadoPor = [];
    $receitas = consultar(
        'SELECT id_cardapio, id_ingrediente, quantidade_utilizada FROM cardapio_ingrediente WHERE id_cardapio IN (' . marcadores(count($ids)) . ')',
        $ids
    );
    foreach ($receitas as $r) {
        $ing = (int) $r['id_ingrediente'];
        $necessario[$ing] = ($necessario[$ing] ?? 0) + (int) $r['quantidade_utilizada'] * $carrinho[(int) $r['id_cardapio']];
        $usadoPor[$ing][] = $produtos[(int) $r['id_cardapio']]['nome'];
    }

    return em_transacao(function () use ($cliente, $carrinho, $observacoes, $produtos, $necessario, $usadoPor) {
        if ($necessario) {
            $ingredientes = array_keys($necessario);
            $estoque = [];
            $linhas = consultar(
                'SELECT id_ingrediente, quantidade FROM estoque WHERE id_ingrediente IN (' . marcadores(count($ingredientes)) . ') FOR UPDATE',
                $ingredientes
            );
            foreach ($linhas as $l) {
                $estoque[(int) $l['id_ingrediente']] = (int) $l['quantidade'];
            }
            $semEstoque = [];
            foreach ($necessario as $ing => $qtd) {
                if (($estoque[$ing] ?? 0) < $qtd) {
                    array_push($semEstoque, ...$usadoPor[$ing]);
                }
            }
            if ($semEstoque) {
                throw new EstoqueInsuficiente('Não há estoque suficiente para: '
                    . implode(', ', array_unique($semEstoque)) . '. Diminua a quantidade ou escolha outro item.');
            }
        }

        // Valores calculados em centavos (inteiros) para não ter erro de arredondamento.
        $totalCentavos = 0;
        foreach ($carrinho as $id => $qtd) {
            $totalCentavos += centavos($produtos[$id]['preco']) * $qtd;
        }

        alterar(
            'INSERT INTO pedido (id_cliente, nome_cliente, valor, status) VALUES (?, ?, ?, ?)',
            [(int) $cliente['id'], $cliente['nome'], reais($totalCentavos), STATUS_AGUARDANDO]
        );
        $numero = ultimo_id();

        foreach ($carrinho as $id => $qtd) {
            $obs = mb_substr(trim((string) ($observacoes[$id] ?? '')), 0, 255);
            alterar(
                'INSERT INTO itens_pedido (numero_do_pedido, id_cardapio, produto, quantidade, valor, observacao) VALUES (?, ?, ?, ?, ?, ?)',
                [$numero, $id, $produtos[$id]['nome'], $qtd, reais(centavos($produtos[$id]['preco']) * $qtd), $obs !== '' ? $obs : null]
            );
        }

        foreach ($necessario as $ing => $qtd) {
            alterar('UPDATE estoque SET quantidade = quantidade - ? WHERE id_ingrediente = ?', [$qtd, $ing]);
            alterar('INSERT INTO pedido_estoque (numero_do_pedido, id_ingrediente, quantidade) VALUES (?, ?, ?)', [$numero, $ing, $qtd]);
        }

        alterar(
            'INSERT INTO pagamento (data_hora, forma_de_pagamento, valor, numero_do_pedido, id_cliente) VALUES (NOW(), ?, ?, ?, ?)',
            ['PIX', reais($totalCentavos), $numero, (int) $cliente['id']]
        );
        return $numero;
    });
}

/**
 * Executa uma ação (aceitar, recusar, confirmar, cancelar, concluir).
 * Retorna a mensagem de sucesso; lança exceção se não for permitido.
 */
function alterar_status_pedido(int $numero, string $acao, array $usuario): string
{
    $regra = ACOES_PEDIDO[$acao] ?? null;
    if ($regra === null || !in_array($usuario['perfil'], $regra['perfis'], true)) {
        throw new InvalidArgumentException('Ação não permitida.');
    }

    em_transacao(function () use ($numero, $regra, $usuario) {
        $sql = 'UPDATE pedido SET status = ? WHERE numero_do_pedido = ? AND status IN (' . marcadores(count($regra['de'])) . ')';
        $parametros = [$regra['para'], $numero, ...$regra['de']];
        if ($usuario['perfil'] === PERFIL_CLIENTE) {
            // O cliente só pode mexer nos próprios pedidos.
            $sql .= ' AND id_cliente = ?';
            $parametros[] = (int) $usuario['id'];
        }
        if (alterar($sql, $parametros) === 0) {
            throw new InvalidArgumentException('Este pedido não pode mais ser alterado. Atualize a página.');
        }
        if ($regra['para'] === STATUS_CANCELADO) {
            devolver_estoque($numero);
        }
    });
    return sprintf($regra['mensagem'], $numero);
}

/** Devolve ao estoque exatamente o que o pedido consumiu. */
function devolver_estoque(int $numero): void
{
    alterar(
        'UPDATE estoque e JOIN pedido_estoque pe ON pe.id_ingrediente = e.id_ingrediente
            SET e.quantidade = e.quantidade + pe.quantidade
          WHERE pe.numero_do_pedido = ?',
        [$numero]
    );
    alterar('DELETE FROM pedido_estoque WHERE numero_do_pedido = ?', [$numero]);
}

/**
 * Lista pedidos com seus itens.
 *
 * @param array $filtros 'status' (string|array), 'desde' (data/hora), 'id_cliente'
 */
function listar_pedidos(array $filtros = [], int $limite = 200): array
{
    $where = [];
    $parametros = [];
    if (!empty($filtros['status'])) {
        $status = (array) $filtros['status'];
        $where[] = 'p.status IN (' . marcadores(count($status)) . ')';
        array_push($parametros, ...$status);
    }
    if (!empty($filtros['desde'])) {
        $where[] = 'p.data_pedido >= ?';
        $parametros[] = str_replace('T', ' ', $filtros['desde']);
    }
    if (isset($filtros['id_cliente'])) {
        $where[] = 'p.id_cliente = ?';
        $parametros[] = (int) $filtros['id_cliente'];
    }
    $sql = 'SELECT p.numero_do_pedido, p.id_cliente, p.nome_cliente, p.valor, p.status, p.data_pedido
              FROM pedido p'
        . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
        . ' ORDER BY p.numero_do_pedido DESC LIMIT ' . $limite;
    $pedidos = consultar($sql, $parametros);
    if (!$pedidos) {
        return [];
    }

    $numeros = array_map(fn($p) => (int) $p['numero_do_pedido'], $pedidos);
    $itens = [];
    $linhas = consultar(
        'SELECT numero_do_pedido, produto, quantidade, observacao FROM itens_pedido
          WHERE numero_do_pedido IN (' . marcadores(count($numeros)) . ') ORDER BY id',
        $numeros
    );
    foreach ($linhas as $i) {
        $itens[(int) $i['numero_do_pedido']][] = $i;
    }
    foreach ($pedidos as &$p) {
        $p['itens'] = $itens[(int) $p['numero_do_pedido']] ?? [];
    }
    return $pedidos;
}

function contar_pedidos_aguardando(): int
{
    return (int) (consultar_um('SELECT COUNT(*) AS total FROM pedido WHERE status = ?', [STATUS_AGUARDANDO])['total'] ?? 0);
}

// ------------------------------------------------------------- Exibição

/** Lista de itens do pedido, já escapada para HTML. */
function html_itens_pedido(array $pedido): string
{
    $html = '<ul class="lista-itens">';
    foreach ($pedido['itens'] as $item) {
        $html .= '<li>' . (int) $item['quantidade'] . 'x ' . e($item['produto']) . '</li>';
    }
    return $html . '</ul>';
}

function html_observacoes_pedido(array $pedido): string
{
    $obs = [];
    foreach ($pedido['itens'] as $item) {
        if (!empty($item['observacao'])) {
            $obs[] = '<li><strong>' . e($item['produto']) . ':</strong> ' . e($item['observacao']) . '</li>';
        }
    }
    return $obs ? '<ul class="lista-itens">' . implode('', $obs) . '</ul>' : '<span class="texto-suave">—</span>';
}

function html_status(string $status): string
{
    $classes = [
        STATUS_AGUARDANDO => 'aguardando',
        STATUS_PREPARO => 'preparo',
        STATUS_A_CAMINHO => 'caminho',
        STATUS_CONCLUIDO => 'concluido',
        STATUS_CANCELADO => 'cancelado',
    ];
    return '<span class="status status-' . ($classes[$status] ?? 'outro') . '">' . e($status) . '</span>';
}

// ------------------------------------------------------------- Carrinho

/** Mantém só ids e quantidades válidos (inteiros positivos, com limite). */
function normalizar_carrinho(array $carrinho): array
{
    $limpo = [];
    foreach ($carrinho as $id => $qtd) {
        $id = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $qtd = filter_var($qtd, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id !== false && $qtd !== false) {
            $limpo[$id] = min($qtd, MAX_QUANTIDADE_ITEM);
        }
    }
    return $limpo;
}

function centavos(string|float|int $valor): int
{
    return (int) round(((float) $valor) * 100);
}

function reais(int $centavos): string
{
    return number_format($centavos / 100, 2, '.', '');
}
