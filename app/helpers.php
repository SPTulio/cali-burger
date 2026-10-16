<?php
// Funções auxiliares usadas pelas páginas.

declare(strict_types=1);

/** Escapa texto para exibir em HTML (proteção contra XSS). */
function e(mixed $valor): string
{
    return htmlspecialchars((string) ($valor ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function dinheiro(mixed $valor): string
{
    return 'R$ ' . number_format((float) $valor, 2, ',', '.');
}

function data_hora(?string $valor): string
{
    return $valor ? date('d/m/Y H:i', strtotime($valor)) : '';
}

function data_br(?string $valor): string
{
    return $valor ? date('d/m/Y', strtotime($valor)) : '';
}

function redirecionar(string $url): never
{
    header('Location: ' . $url);
    exit();
}

/** Lê um campo de texto do formulário, sem espaços nas pontas. */
function campo(string $nome, string $padrao = ''): string
{
    $valor = $_POST[$nome] ?? $padrao;
    return is_string($valor) ? trim($valor) : $padrao;
}

function campo_get(string $nome, string $padrao = ''): string
{
    $valor = $_GET[$nome] ?? $padrao;
    return is_string($valor) ? trim($valor) : $padrao;
}

function campo_inteiro(string $nome, ?array $origem = null): ?int
{
    $origem ??= $_POST;
    $valor = $origem[$nome] ?? null;
    if (is_string($valor) && preg_match('/^-?\d+$/', trim($valor))) {
        return (int) $valor;
    }
    return is_int($valor) ? $valor : null;
}

/** Converte "12,50" ou "12.50" em 12.5. Retorna null se for inválido. */
function campo_preco(string $nome): ?float
{
    $texto = str_replace(['R$', ' '], '', campo($nome));
    if (str_contains($texto, ',')) {
        $texto = str_replace(['.', ','], ['', '.'], $texto);
    }
    return preg_match('/^\d+(\.\d{1,2})?$/', $texto) ? (float) $texto : null;
}

// ---------------------------------------------------------- Mensagens

/** Guarda uma mensagem para exibir na próxima página (padrão Post/Redirect/Get). */
function mensagem(string $texto, string $tipo = 'sucesso'): void
{
    $_SESSION['mensagens'][] = ['tipo' => $tipo, 'texto' => $texto];
}

function exibir_mensagens(): string
{
    $html = '';
    foreach ($_SESSION['mensagens'] ?? [] as $m) {
        $html .= '<div class="alerta alerta-' . e($m['tipo']) . '" role="alert">' . e($m['texto']) . '</div>';
    }
    unset($_SESSION['mensagens']);
    return $html;
}

// ---------------------------------------------------------- Validações

function somente_digitos(string $texto): string
{
    return preg_replace('/\D/', '', $texto);
}

function valida_cpf(string $cpf): bool
{
    $cpf = somente_digitos($cpf);
    if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
        return false;
    }
    for ($t = 9; $t < 11; $t++) {
        $soma = 0;
        for ($c = 0; $c < $t; $c++) {
            $soma += (int) $cpf[$c] * (($t + 1) - $c);
        }
        $digito = ((10 * $soma) % 11) % 10;
        if ((int) $cpf[$t] !== $digito) {
            return false;
        }
    }
    return true;
}

function valida_data_nascimento(string $data): bool
{
    $d = DateTime::createFromFormat('!Y-m-d', $data);
    if (!$d || $d->format('Y-m-d') !== $data) {
        return false;
    }
    $hoje = new DateTime('today');
    return $d <= $hoje && $d >= new DateTime('1900-01-01');
}

function valida_email(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false && strlen($email) <= 255;
}

/** Senha: mínimo de 6 caracteres (as contas de exemplo usam "123456"). */
function valida_senha(string $senha): bool
{
    return mb_strlen($senha) >= 6 && mb_strlen($senha) <= 72;
}

function formata_cpf(?string $cpf): string
{
    $d = somente_digitos((string) $cpf);
    return strlen($d) === 11
        ? substr($d, 0, 3) . '.' . substr($d, 3, 3) . '.' . substr($d, 6, 3) . '-' . substr($d, 9)
        : (string) $cpf;
}

// ---------------------------------------------------------- Erros

function erro_fatal(string $mensagem, ?Throwable $causa = null): never
{
    if ($causa) {
        error_log('[Cali Burger] ' . $mensagem . ' ' . $causa);
    }
    http_response_code(500);
    $detalhe = (MODO_DEBUG && $causa) ? '<pre>' . e($causa->getMessage()) . '</pre>' : '';
    echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><title>Erro - Cali Burger</title></head>'
        . '<body style="font-family: sans-serif; text-align: center; padding: 60px;">'
        . '<h1>Ops! Algo deu errado.</h1><p>' . e($mensagem) . '</p>' . $detalhe
        . '<p><a href="index.php">Voltar ao início</a></p></body></html>';
    exit();
}

function pagina_proibida(): never
{
    http_response_code(403);
    echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><title>Acesso negado - Cali Burger</title></head>'
        . '<body style="font-family: sans-serif; text-align: center; padding: 60px;">'
        . '<h1>Acesso negado</h1><p>Você não tem permissão para acessar esta página.</p>'
        . '<p><a href="index.php">Voltar ao início</a></p></body></html>';
    exit();
}
