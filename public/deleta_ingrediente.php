<?php
require_once __DIR__ . '/../app/bootstrap.php';
exigir_perfil(PERFIL_BALCONISTA);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // As receitas que usam o ingrediente são atualizadas pelo ON DELETE CASCADE.
    $excluidos = alterar('DELETE FROM estoque WHERE id_ingrediente = ?', [campo_inteiro('id') ?? 0]);
    mensagem($excluidos ? 'Ingrediente excluído.' : 'Ingrediente não encontrado.', $excluidos ? 'sucesso' : 'erro');
}
redirecionar('estoque.php');
