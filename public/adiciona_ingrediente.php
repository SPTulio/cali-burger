<?php
// Adiciona um ingrediente novo ou soma quantidade a um existente.
require_once __DIR__ . '/../app/bootstrap.php';
exigir_perfil(PERFIL_BALCONISTA);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirecionar('estoque.php');
}

$nome = campo('nome_ingrediente');
$quantidade = campo_inteiro('quantidade');

if ($nome === '' || mb_strlen($nome) > 50) {
    mensagem('Informe o nome do ingrediente (até 50 caracteres).', 'erro');
} elseif ($quantidade === null || $quantidade < 1 || $quantidade > 100000) {
    mensagem('Informe uma quantidade entre 1 e 100.000.', 'erro');
} else {
    // INSERT ... ON DUPLICATE KEY UPDATE: cria ou soma em uma única operação.
    alterar(
        'INSERT INTO estoque (nome_ingrediente, quantidade) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE quantidade = quantidade + VALUES(quantidade)',
        [$nome, $quantidade]
    );
    mensagem("Estoque de \"$nome\" atualizado (+$quantidade).");
}
redirecionar('estoque.php');
