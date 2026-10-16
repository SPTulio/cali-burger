<?php
// Conexão com o MySQL e funções auxiliares para consultas parametrizadas.
// Todas as consultas usam prepared statements: os valores nunca são
// concatenados no SQL, o que impede SQL injection.

declare(strict_types=1);

function db(): mysqli
{
    static $conexao = null;
    if ($conexao === null) {
        // Faz o mysqli lançar exceções em vez de falhar em silêncio.
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        try {
            $conexao = new mysqli(DB_HOST, DB_USUARIO, DB_SENHA, DB_NOME, DB_PORT);
            $conexao->set_charset('utf8mb4');
            $conexao->query("SET time_zone = '" . date('P') . "'");
        } catch (mysqli_sql_exception $e) {
            erro_fatal('Não foi possível conectar ao banco de dados.', $e);
        }
    }
    return $conexao;
}

/** Monta a string de tipos do bind_param a partir dos valores. */
function tipos_parametros(array $parametros): string
{
    $tipos = '';
    foreach ($parametros as $valor) {
        $tipos .= is_int($valor) ? 'i' : (is_float($valor) ? 'd' : 's');
    }
    return $tipos;
}

function executar(string $sql, array $parametros = []): mysqli_stmt
{
    $stmt = db()->prepare($sql);
    if ($parametros) {
        $stmt->bind_param(tipos_parametros($parametros), ...array_values($parametros));
    }
    $stmt->execute();
    return $stmt;
}

/** Retorna todas as linhas da consulta como arrays associativos. */
function consultar(string $sql, array $parametros = []): array
{
    $resultado = executar($sql, $parametros)->get_result();
    return $resultado ? $resultado->fetch_all(MYSQLI_ASSOC) : [];
}

/** Retorna a primeira linha da consulta, ou null. */
function consultar_um(string $sql, array $parametros = []): ?array
{
    $linhas = consultar($sql, $parametros);
    return $linhas[0] ?? null;
}

/** Executa INSERT/UPDATE/DELETE e retorna o número de linhas afetadas. */
function alterar(string $sql, array $parametros = []): int
{
    return executar($sql, $parametros)->affected_rows;
}

function ultimo_id(): int
{
    return (int) db()->insert_id;
}

/** Executa a função dentro de uma transação: tudo é salvo, ou nada é salvo. */
function em_transacao(callable $acao): mixed
{
    $conexao = db();
    $conexao->begin_transaction();
    try {
        $resultado = $acao();
        $conexao->commit();
        return $resultado;
    } catch (Throwable $e) {
        $conexao->rollback();
        throw $e;
    }
}

/** Gera "?, ?, ?" para usar em cláusulas IN. */
function marcadores(int $quantidade): string
{
    return implode(', ', array_fill(0, $quantidade, '?'));
}
