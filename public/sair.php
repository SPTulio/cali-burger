<?php
require_once __DIR__ . '/../app/bootstrap.php';

$perfil = usuario_logado()['perfil'] ?? PERFIL_CLIENTE;
sair();
redirecionar(PAGINA_LOGIN[$perfil]);
