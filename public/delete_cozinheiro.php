<?php
require_once __DIR__ . '/../app/bootstrap.php';
exigir_perfil(PERFIL_BALCONISTA);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $excluidos = alterar('DELETE FROM cozinheiro WHERE cpf = ?', [somente_digitos(campo('cpf'))]);
    mensagem($excluidos ? 'Cozinheiro excluído.' : 'Cozinheiro não encontrado.', $excluidos ? 'sucesso' : 'erro');
}
redirecionar('main.php');
