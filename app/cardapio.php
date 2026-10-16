<?php
// Regras de negócio do cardápio: disponibilidade pelo estoque,
// cadastro de itens com receita (ingredientes) e upload de imagens.

declare(strict_types=1);

function listar_categorias(): array
{
    return consultar('SELECT id, nome FROM categoria ORDER BY id');
}

function listar_ingredientes(): array
{
    return consultar('SELECT id_ingrediente, nome_ingrediente, quantidade FROM estoque ORDER BY nome_ingrediente');
}

/**
 * Itens do cardápio com categoria, ingredientes e quantas porções o
 * estoque atual permite fazer ("porcoes" = null quando o item não tem receita).
 */
function listar_cardapio(?int $categoriaId = null): array
{
    $sql = 'SELECT c.id, c.nome, c.descricao, c.preco, c.imagem, c.categoria_id, cat.nome AS categoria_nome,
                   GROUP_CONCAT(e.nome_ingrediente ORDER BY e.nome_ingrediente SEPARATOR \', \') AS ingredientes,
                   MIN(FLOOR(e.quantidade / ci.quantidade_utilizada)) AS porcoes
              FROM cardapio c
              LEFT JOIN categoria cat ON cat.id = c.categoria_id
              LEFT JOIN cardapio_ingrediente ci ON ci.id_cardapio = c.id
              LEFT JOIN estoque e ON e.id_ingrediente = ci.id_ingrediente';
    $parametros = [];
    if ($categoriaId !== null) {
        $sql .= ' WHERE c.categoria_id = ?';
        $parametros[] = $categoriaId;
    }
    $sql .= ' GROUP BY c.id, c.nome, c.descricao, c.preco, c.imagem, c.categoria_id, cat.nome
              ORDER BY c.categoria_id, c.nome';

    $itens = consultar($sql, $parametros);
    foreach ($itens as &$item) {
        $item['porcoes'] = $item['porcoes'] === null ? null : (int) $item['porcoes'];
        $item['disponivel'] = $item['porcoes'] === null || $item['porcoes'] > 0;
    }
    return $itens;
}

function buscar_item_cardapio(int $id): ?array
{
    return consultar_um('SELECT id, nome, descricao, preco, imagem, categoria_id FROM cardapio WHERE id = ?', [$id]);
}

/** Receita do item: [id_ingrediente => quantidade utilizada]. */
function receita_do_item(int $id): array
{
    $receita = [];
    foreach (consultar('SELECT id_ingrediente, quantidade_utilizada FROM cardapio_ingrediente WHERE id_cardapio = ?', [$id]) as $r) {
        $receita[(int) $r['id_ingrediente']] = (int) $r['quantidade_utilizada'];
    }
    return $receita;
}

/**
 * Valida e salva um item do cardápio com sua receita.
 * Retorna o id do item. Lança InvalidArgumentException com a mensagem do erro.
 */
function salvar_item_cardapio(?int $id, array $dados, array $receita, ?array $imagemEnviada): int
{
    $nome = trim((string) ($dados['nome'] ?? ''));
    $descricao = trim((string) ($dados['descricao'] ?? ''));
    $preco = $dados['preco'] ?? null;
    $categoria = (int) ($dados['categoria'] ?? 0);

    if ($nome === '' || mb_strlen($nome) > 100) {
        throw new InvalidArgumentException('Informe o nome do item (até 100 caracteres).');
    }
    if (mb_strlen($descricao) > 255) {
        throw new InvalidArgumentException('A descrição pode ter no máximo 255 caracteres.');
    }
    if ($preco === null || $preco <= 0 || $preco > 9999) {
        throw new InvalidArgumentException('Informe um preço válido, por exemplo 22,90.');
    }
    if (!consultar_um('SELECT id FROM categoria WHERE id = ?', [$categoria])) {
        throw new InvalidArgumentException('Selecione uma categoria.');
    }
    $existentes = array_column(listar_ingredientes(), 'id_ingrediente');
    $receitaLimpa = [];
    foreach ($receita as $ing => $qtd) {
        $ing = (int) $ing;
        $qtd = (int) $qtd;
        if ($qtd < 0 || $qtd > 1000 || !in_array($ing, array_map('intval', $existentes), true)) {
            throw new InvalidArgumentException('Quantidade de ingrediente inválida.');
        }
        if ($qtd > 0) {
            $receitaLimpa[$ing] = $qtd;
        }
    }

    $imagem = $imagemEnviada ? salvar_imagem_enviada($imagemEnviada) : null;

    return em_transacao(function () use ($id, $nome, $descricao, $preco, $categoria, $receitaLimpa, $imagem) {
        $precoTexto = number_format((float) $preco, 2, '.', '');
        if ($id === null) {
            alterar(
                'INSERT INTO cardapio (nome, descricao, preco, imagem, categoria_id) VALUES (?, ?, ?, ?, ?)',
                [$nome, $descricao !== '' ? $descricao : null, $precoTexto, $imagem, $categoria]
            );
            $id = ultimo_id();
        } else {
            alterar(
                'UPDATE cardapio SET nome = ?, descricao = ?, preco = ?, categoria_id = ?, imagem = COALESCE(?, imagem) WHERE id = ?',
                [$nome, $descricao !== '' ? $descricao : null, $precoTexto, $categoria, $imagem, $id]
            );
            alterar('DELETE FROM cardapio_ingrediente WHERE id_cardapio = ?', [$id]);
        }
        foreach ($receitaLimpa as $ing => $qtd) {
            alterar('INSERT INTO cardapio_ingrediente (id_cardapio, id_ingrediente, quantidade_utilizada) VALUES (?, ?, ?)', [$id, $ing, $qtd]);
        }
        return $id;
    });
}

/**
 * Guarda a imagem enviada no formulário com um nome aleatório.
 * Aceita somente PNG, JPEG e WebP de até 2 MB, conferindo o conteúdo real do arquivo.
 */
function salvar_imagem_enviada(array $arquivo): ?string
{
    if (($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($arquivo['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($arquivo['tmp_name'])) {
        throw new InvalidArgumentException('Não foi possível enviar a imagem.');
    }
    if ($arquivo['size'] > TAMANHO_MAXIMO_IMAGEM) {
        throw new InvalidArgumentException('A imagem deve ter no máximo 2 MB.');
    }
    $extensoes = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
    $tipo = (new finfo(FILEINFO_MIME_TYPE))->file($arquivo['tmp_name']);
    if (!isset($extensoes[$tipo]) || @getimagesize($arquivo['tmp_name']) === false) {
        throw new InvalidArgumentException('Envie uma imagem PNG, JPG ou WebP.');
    }
    $nome = bin2hex(random_bytes(8)) . '.' . $extensoes[$tipo];
    if (!move_uploaded_file($arquivo['tmp_name'], PASTA_IMAGENS_LANCHES . '/' . $nome)) {
        throw new InvalidArgumentException('Não foi possível salvar a imagem.');
    }
    return $nome;
}

/** Caminho da imagem do item para usar no HTML (já escapado), ou null. */
function url_imagem(?string $arquivo): ?string
{
    if (!$arquivo || !preg_match('/^[\w.-]+$/', $arquivo) || !is_file(PASTA_IMAGENS_LANCHES . '/' . $arquivo)) {
        return null;
    }
    return 'imagens/lanches/' . rawurlencode($arquivo);
}

function html_disponibilidade(array $item): string
{
    if (!$item['disponivel']) {
        return '<span class="status status-cancelado">Esgotado</span>';
    }
    if ($item['porcoes'] !== null && $item['porcoes'] <= 5) {
        return '<span class="status status-aguardando">Últimas ' . $item['porcoes'] . '</span>';
    }
    return '<span class="status status-concluido">Disponível</span>';
}

/** Lê os campos do formulário de item do cardápio: [dados do item, receita]. */
function dados_item_do_formulario(): array
{
    $item = [
        'nome' => campo('nome'),
        'descricao' => campo('descricao'),
        'preco' => campo('preco'),
        'categoria_id' => campo_inteiro('categoria') ?? 0,
        'imagem' => null,
    ];
    $receita = [];
    foreach (is_array($_POST['ingredientes'] ?? null) ? $_POST['ingredientes'] : [] as $ing => $qtd) {
        if (is_numeric($qtd)) {
            $receita[(int) $ing] = (int) $qtd;
        }
    }
    return [$item, $receita];
}
