<?php
require_once __DIR__ . '/../app/bootstrap.php';
exigir_perfil(PERFIL_BALCONISTA);

$item = ['nome' => '', 'descricao' => '', 'preco' => '', 'categoria_id' => '', 'imagem' => null];
$receita = [];
$erro = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$item, $receita] = dados_item_do_formulario();
    try {
        salvar_item_cardapio(null, ['nome' => $item['nome'], 'descricao' => $item['descricao'], 'preco' => campo_preco('preco'),
            'categoria' => $item['categoria_id']], $receita, $_FILES['imagem'] ?? null);
        mensagem('Item adicionado ao cardápio!');
        redirecionar('cardapio.php');
    } catch (InvalidArgumentException $e) {
        $erro = $e->getMessage();
    }
}

$titulo = 'Adicionar item ao cardápio';
require __DIR__ . '/../app/partials/form_item_cardapio.php';
