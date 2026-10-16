<?php
// Cadastro e edição de funcionários (balconistas/donos e cozinheiros).
// As duas tabelas têm os mesmos campos; a chave é o CPF.

declare(strict_types=1);

const TABELAS_FUNCIONARIOS = [
    PERFIL_BALCONISTA => 'balconista_dono',
    PERFIL_COZINHEIRO => 'cozinheiro',
];

const SEXOS = ['Masculino', 'Feminino', 'Outro'];

/** Valida os dados pessoais. Retorna a lista de erros (vazia se estiver tudo certo). */
function validar_dados_funcionario(array $dados): array
{
    $erros = [];
    if ($dados['nome'] === '' || mb_strlen($dados['nome']) > 100) {
        $erros[] = 'Informe o nome (até 100 caracteres).';
    }
    if (!valida_data_nascimento($dados['data_nasc'])) {
        $erros[] = 'Data de nascimento inválida.';
    }
    if (!valida_email($dados['email'])) {
        $erros[] = 'E-mail inválido.';
    }
    if (!in_array($dados['sexo'], SEXOS, true)) {
        $erros[] = 'Selecione o sexo.';
    }
    return $erros;
}

function dados_funcionario_do_formulario(): array
{
    return [
        'cpf' => somente_digitos(campo('cpf')),
        'nome' => campo('nome'),
        'data_nasc' => campo('data_nasc'),
        'email' => campo('email'),
        'sexo' => campo('sexo'),
    ];
}

/** Cadastra um funcionário. Retorna a lista de erros (vazia = sucesso). */
function cadastrar_funcionario(string $perfil): array
{
    $tabela = TABELAS_FUNCIONARIOS[$perfil];
    $dados = dados_funcionario_do_formulario();
    $senha = (string) ($_POST['senha'] ?? '');

    $erros = validar_dados_funcionario($dados);
    if (!valida_cpf($dados['cpf'])) {
        $erros[] = 'CPF inválido.';
    } elseif (consultar_um("SELECT cpf FROM $tabela WHERE cpf = ?", [$dados['cpf']])) {
        $erros[] = 'CPF já cadastrado.';
    }
    if (!valida_senha($senha)) {
        $erros[] = 'A senha deve ter pelo menos 6 caracteres.';
    } elseif ($senha !== ($_POST['confirmar_senha'] ?? '')) {
        $erros[] = 'As senhas não coincidem.';
    }
    if ($erros) {
        return $erros;
    }
    // A senha é sempre gravada com hash. Antes, o cadastro gravava o hash mas o
    // login comparava texto puro, então funcionários novos não conseguiam entrar.
    alterar(
        "INSERT INTO $tabela (cpf, nome, data_nasc, email, sexo, senha) VALUES (?, ?, ?, ?, ?, ?)",
        [$dados['cpf'], $dados['nome'], $dados['data_nasc'], $dados['email'], $dados['sexo'], password_hash($senha, PASSWORD_DEFAULT)]
    );
    return [];
}

function listar_funcionarios(string $perfil): array
{
    $tabela = TABELAS_FUNCIONARIOS[$perfil];
    return consultar("SELECT cpf, nome, data_nasc, email, sexo FROM $tabela ORDER BY nome");
}

function buscar_funcionario(string $perfil, string $cpf): ?array
{
    $tabela = TABELAS_FUNCIONARIOS[$perfil];
    return consultar_um("SELECT cpf, nome, data_nasc, email, sexo FROM $tabela WHERE cpf = ?", [$cpf]);
}

/** Atualiza os dados do próprio funcionário (e a senha, se pedida). Retorna os erros. */
function atualizar_funcionario(string $perfil, string $cpf): array
{
    $tabela = TABELAS_FUNCIONARIOS[$perfil];
    $dados = dados_funcionario_do_formulario();
    $erros = validar_dados_funcionario($dados);
    if (!$erros && ($erroSenha = processar_troca_senha($tabela, 'cpf', $cpf))) {
        $erros[] = $erroSenha;
    }
    if ($erros) {
        return $erros;
    }
    alterar(
        "UPDATE $tabela SET nome = ?, data_nasc = ?, email = ?, sexo = ? WHERE cpf = ?",
        [$dados['nome'], $dados['data_nasc'], $dados['email'], $dados['sexo'], $cpf]
    );
    $_SESSION['usuario']['nome'] = $dados['nome'];
    return [];
}

function html_campos_funcionario(array $dados, bool $comCpf): string
{
    $html = '';
    if ($comCpf) {
        $html .= '<label>CPF<input type="text" name="cpf" value="' . e($dados['cpf'] ?? '') . '" inputmode="numeric" placeholder="000.000.000-00" required></label>';
    }
    $html .= '<label>Nome<input type="text" name="nome" value="' . e($dados['nome'] ?? '') . '" required maxlength="100"></label>'
        . '<label>Nascimento<input type="date" name="data_nasc" value="' . e($dados['data_nasc'] ?? '') . '" max="' . date('Y-m-d') . '" required></label>'
        . '<label>E-mail<input type="email" name="email" value="' . e($dados['email'] ?? '') . '" required maxlength="255"></label>'
        . '<label>Sexo<select name="sexo" required><option value="">Selecione</option>';
    foreach (SEXOS as $sexo) {
        $html .= '<option value="' . e($sexo) . '"' . (($dados['sexo'] ?? '') === $sexo ? ' selected' : '') . '>' . e($sexo) . '</option>';
    }
    return $html . '</select></label>';
}
