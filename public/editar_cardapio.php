<?php
require_once __DIR__ . '/../app/bootstrap.php';
exigir_perfil(PERFIL_BALCONISTA);

$id = campo_inteiro('id', $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET) ?? 0;
$item = buscar_item_cardapio($id);
if (!$item) {
    mensagem('Item do cardápio não encontrado.', 'erro');
    redirecionar('cardapio.php');
}
$receita = receita_do_item($id);
$erro = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $imagemAtual = $item['imagem'];
    [$item, $receita] = dados_item_do_formulario();
    $item['id'] = $id;
    $item['imagem'] = $imagemAtual;
    try {
        salvar_item_cardapio($id, ['nome' => $item['nome'], 'descricao' => $item['descricao'], 'preco' => campo_preco('preco'),
            'categoria' => $item['categoria_id']], $receita, $_FILES['imagem'] ?? null);
        mensagem('Item atualizado!');
        redirecionar('cardapio.php');
    } catch (InvalidArgumentException $e) {
        $erro = $e->getMessage();
    }
}

$titulo = 'Editar item do cardápio';
require __DIR__ . '/../app/partials/form_item_cardapio.php';
