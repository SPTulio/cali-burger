<?php
// Página inicial: leva cada usuário para a sua área.
require_once __DIR__ . '/../app/bootstrap.php';

$usuario = usuario_logado();
redirecionar($usuario ? PAGINA_INICIAL[$usuario['perfil']] : 'cardapio_cliente.php');
