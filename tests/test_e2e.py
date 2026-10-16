"""Testes de ponta a ponta do Cali Burger (sistema rodando no Docker)."""

import re
import struct
import uuid
import zlib

from conftest import (CPF_BALCONISTA, CPF_COZINHEIRO, SENHA_DEMO, Navegador, cpf_valido, fazer_pedido, novo_cliente,
                      numero_do_pedido)

# Ids dos dados de exemplo (database/02_dados_exemplo.sql)
CALI_CLASSICO, CALI_DUPLO, X_BURGER, FRITAS, NUGGETS, SUCO, REFRI, AGUA = range(1, 9)
PAO, HAMBURGUER, CHEDDAR, ALFACE, TOMATE, ING_SUCO, ING_REFRI, ING_AGUA, BATATA, NUGGET = range(1, 11)

PAGINAS = {
    "cliente": ["cardapio_cliente.php", "carrinho.php", "meus_pedidos.php", "perfil_cliente.php",
                "editar_cliente.php", "editar_endereco.php"],
    "balconista": ["pedidos.php", "pedidos.php?visao=todos", "cardapio.php", "adiciona_item_cardapio.php",
                   "editar_cardapio.php?id=1", "estoque.php", "editar_ingrediente.php?id=1", "relatorio.php",
                   "relatorio.php?start_date=2020-01-01&end_date=2099-12-31", "main.php",
                   "perfil_balconista_dono.php", "editar_balconista_dono.php"],
    "cozinheiro": ["pedidos_cozinheiro.php", "cardapio_cozinheiro.php", "perfil_cozinheiro.php", "editar_cozinheiro.php"],
}


# ------------------------------------------------------------------ Páginas e acesso

def test_paginas_publicas(navegador):
    r = navegador.get("cardapio_cliente.php")
    assert r.status_code == 200
    assert "Cali Clássico" in r.text
    assert "Esgotado" in r.text  # Suco de Uva está sem estoque nos dados de exemplo
    for pagina in ["login.php", "login_balconista.php", "login_cozinheiro.php", "cadastro.php"]:
        assert navegador.get(pagina).status_code == 200


def test_todas_as_paginas_abrem_sem_erros(cliente, balconista, cozinheiro):
    for perfil, navegador in [("cliente", cliente), ("balconista", balconista), ("cozinheiro", cozinheiro)]:
        for pagina in PAGINAS[perfil]:
            r = navegador.get(pagina)
            assert r.status_code == 200, f"{perfil} -> {pagina}: HTTP {r.status_code}"
            assert "Algo deu errado" not in r.text, f"{perfil} -> {pagina}: erro na página"


def test_paginas_internas_exigem_login(navegador):
    assert navegador.get("pedidos.php").url.endswith("login_balconista.php")
    assert navegador.get("pedidos_cozinheiro.php").url.endswith("login_cozinheiro.php")
    assert navegador.get("meus_pedidos.php").url.endswith("login.php")
    assert navegador.get("pagamento.php").url.endswith("login.php")


def test_cada_perfil_so_acessa_a_propria_area(cliente, cozinheiro):
    # Antes, qualquer pessoa logada (até cliente) abria as telas do balconista.
    for pagina in ["estoque.php", "main.php", "pedidos.php", "relatorio.php", "pedidos_cozinheiro.php"]:
        assert cliente.get(pagina).status_code == 403, pagina
    for pagina in ["estoque.php", "main.php", "relatorio.php", "meus_pedidos.php"]:
        assert cozinheiro.get(pagina).status_code == 403, pagina


def test_formulario_sem_token_csrf_e_recusado(balconista, db):
    r = balconista.post("deleta_ingrediente.php", {"id": TOMATE}, com_csrf=False)
    assert r.status_code == 400
    assert db.valor("SELECT COUNT(*) FROM estoque WHERE id_ingrediente = %s", TOMATE) == 1


def test_login_com_senha_errada(navegador):
    r = navegador.post("valida_login.php", {"login": "joao", "senha": "errada"})
    assert "Usuário ou senha inválidos" in r.text
    r = navegador.post("valida_login_balconista.php", {"cpf": CPF_BALCONISTA, "senha": "errada"})
    assert "CPF ou senha inválidos" in r.text


# ------------------------------------------------------------------ Senhas e cadastros

def test_senha_antiga_em_texto_puro_e_convertida_para_hash(db):
    # Bancos da versão anterior tinham senhas sem hash (ex.: "123").
    db.executar("UPDATE cozinheiro SET senha = '123' WHERE cpf = '41368460020'")
    Navegador().entrar_funcionario("cozinheiro", "41368460020", "123")
    assert db.valor("SELECT senha FROM cozinheiro WHERE cpf = '41368460020'").startswith("$2y$")


def test_funcionario_cadastrado_pelo_dono_consegue_entrar(balconista, db):
    # Antes: o cadastro gravava a senha com hash e o login comparava texto puro.
    cpf = cpf_valido()
    r = balconista.post("main.php", {
        "form_type": "cozinheiro", "cpf": cpf, "nome": "Cozinheiro Novo", "data_nasc": "1990-01-01",
        "email": "novo@caliburger.com", "sexo": "Outro", "senha": "senha-forte", "confirmar_senha": "senha-forte",
    })
    assert "Cozinheiro cadastrado com sucesso" in r.text
    Navegador().entrar_funcionario("cozinheiro", cpf, "senha-forte")


def test_cadastro_com_aspas_e_html_no_nome(navegador, db):
    # Antes: aspas quebravam o INSERT (SQL injection) e o nome era exibido sem escape (XSS).
    nome = "Pedro D'Ávila <b>negrito</b>"
    login, _ = novo_cliente(navegador, nome)
    assert db.valor("SELECT nome FROM cliente WHERE login = %s", login) == nome
    perfil = navegador.get("perfil_cliente.php").text
    assert "&lt;b&gt;negrito&lt;/b&gt;" in perfil
    assert "<b>negrito</b>" not in perfil


def test_cadastro_valida_cpf_e_senha(navegador):
    r = navegador.post("cadastro.php", {
        "login": "x" + uuid.uuid4().hex[:8], "senha": "123", "confirmar_senha": "123", "nome": "Teste",
        "email": "invalido", "cpf": "11111111111", "data_nascimento": "2999-01-01",
        "estado": "PR", "cidade": "Curitiba", "bairro": "Centro", "logradouro": "Rua 1",
    })
    for erro in ["CPF inválido", "pelo menos 6 caracteres", "E-mail inválido", "Data de nascimento inválida"]:
        assert erro in r.text


def test_cliente_so_edita_os_proprios_dados(db):
    a, b = Navegador(), Navegador()
    login_a, cpf_a = novo_cliente(a, "Cliente A")
    login_b, _ = novo_cliente(b, "Cliente B")
    id_b = db.valor("SELECT id FROM cliente WHERE login = %s", login_b)
    # Antes, o id vinha do formulário: dava para alterar a conta de outra pessoa.
    a.post("editar_cliente.php", {"id": id_b, "login": login_a, "nome": "Nome Alterado", "email": "a@exemplo.com",
                                  "cpf": cpf_a, "data_nasc": "1999-09-09"})
    assert db.valor("SELECT nome FROM cliente WHERE login = %s", login_a) == "Nome Alterado"
    assert db.valor("SELECT nome FROM cliente WHERE login = %s", login_b) == "Cliente B"


def test_troca_de_senha_exige_senha_atual(navegador, db):
    login, cpf = novo_cliente(navegador)
    dados = {"login": login, "nome": "Cliente", "email": "c@exemplo.com", "cpf": cpf, "data_nasc": "1999-09-09",
             "nova_senha": "nova-senha-1", "confirmar_nova_senha": "nova-senha-1"}
    r = navegador.post("editar_cliente.php", {**dados, "senha_atual": "errada"})
    assert "senha atual está incorreta" in r.text
    navegador.post("editar_cliente.php", {**dados, "senha_atual": "segredo123"})
    Navegador().entrar_cliente(login, "nova-senha-1")


# ------------------------------------------------------------------ Pedidos

def test_fluxo_completo_do_pedido(db, balconista, cozinheiro):
    cliente = Navegador()
    novo_cliente(cliente)
    antes = db.estoque()

    # Adicionar itens de duas categorias em momentos diferentes soma no carrinho
    # (antes, a segunda vez apagava o que já estava lá).
    cliente.post("carrinho.php", {"acao": "adicionar", f"quantidade[{CALI_CLASSICO}]": 2})
    cliente.post("carrinho.php", {"acao": "adicionar", f"quantidade[{REFRI}]": 1})
    carrinho = cliente.get("carrinho.php").text
    assert "Cali Clássico" in carrinho and "Refrigerante" in carrinho
    assert "R$ 52,00" in carrinho

    cliente.post("carrinho.php", {"acao": "atualizar", f"quantidade[{CALI_CLASSICO}]": 2, f"quantidade[{REFRI}]": 1,
                                  f"observacao[{CALI_CLASSICO}]": "Sem tomate"})
    assert "R$ 52,00" in cliente.get("pagamento.php").text

    numero = numero_do_pedido(cliente.post("pedido_sucesso.php", {"confirmar_pagamento": "1"}))
    pedido = db.um("SELECT * FROM pedido WHERE numero_do_pedido = %s", numero)
    assert pedido["status"] == "Aguardando aceitação"
    assert float(pedido["valor"]) == 52.0
    assert db.valor("SELECT observacao FROM itens_pedido WHERE numero_do_pedido = %s AND id_cardapio = %s",
                    numero, CALI_CLASSICO) == "Sem tomate"
    assert db.valor("SELECT COUNT(*) FROM pagamento WHERE numero_do_pedido = %s", numero) == 1

    # Baixa no estoque conforme a receita: 2x Cali Clássico + 1x Refrigerante.
    depois = db.estoque()
    for ingrediente, gasto in {PAO: 2, HAMBURGUER: 2, CHEDDAR: 2, ALFACE: 2, TOMATE: 2, ING_REFRI: 1}.items():
        assert antes[ingrediente] - depois[ingrediente] == gasto, ingrediente
    assert db.valor("SELECT SUM(quantidade) FROM pedido_estoque WHERE numero_do_pedido = %s", numero) == 11

    # O pedido aparece para o balconista, com a observação escapada.
    assert f">{numero}<" in balconista.get("pedidos.php").text

    def acao(navegador, nome, voltar):
        return navegador.post("atualizar_status_pedido.php", {"numero_do_pedido": numero, "acao": nome, "voltar": voltar})

    acao(balconista, "aceitar", "pedidos.php")
    assert db.valor("SELECT status FROM pedido WHERE numero_do_pedido = %s", numero) == "Em preparação"
    assert f"Pedido nº {numero}" in cozinheiro.get("pedidos_cozinheiro.php").text

    acao(cozinheiro, "confirmar", "pedidos_cozinheiro.php")
    assert db.valor("SELECT status FROM pedido WHERE numero_do_pedido = %s", numero) == "A caminho"

    r = acao(cliente, "concluir", "meus_pedidos.php")
    assert "marcado como recebido" in r.text
    assert db.valor("SELECT status FROM pedido WHERE numero_do_pedido = %s", numero) == "Concluído"


def test_recusar_pedido_devolve_ingredientes_ao_estoque(db, balconista):
    cliente = Navegador()
    novo_cliente(cliente)
    antes = db.estoque()
    numero = numero_do_pedido(fazer_pedido(cliente, {CALI_DUPLO: 1, FRITAS: 2}))
    assert db.estoque() != antes

    r = balconista.post("atualizar_status_pedido.php", {"numero_do_pedido": numero, "acao": "recusar", "voltar": "pedidos.php"})
    assert "voltaram para o estoque" in r.text
    assert db.valor("SELECT status FROM pedido WHERE numero_do_pedido = %s", numero) == "Cancelado/recusado"
    assert db.estoque() == antes
    assert db.valor("SELECT COUNT(*) FROM pedido_estoque WHERE numero_do_pedido = %s", numero) == 0


def test_pedido_sem_estoque_nao_e_criado(db):
    cliente = Navegador()
    novo_cliente(cliente)
    db.executar("UPDATE estoque SET quantidade = 5 WHERE id_ingrediente = %s", NUGGET)  # 1 porção usa 10
    try:
        total_antes = db.valor("SELECT COUNT(*) FROM pedido")
        antes = db.estoque()
        r = fazer_pedido(cliente, {NUGGETS: 1, AGUA: 1})
        assert "Não há estoque suficiente para: Cali Nuggets" in r.text
        assert db.valor("SELECT COUNT(*) FROM pedido") == total_antes
        assert db.estoque() == antes  # nada foi descontado (nem a água)
    finally:
        db.executar("UPDATE estoque SET quantidade = 100 WHERE id_ingrediente = %s", NUGGET)


def test_cliente_nao_mexe_no_pedido_de_outro(db):
    dono, outro = Navegador(), Navegador()
    novo_cliente(dono)
    novo_cliente(outro)
    numero = numero_do_pedido(fazer_pedido(dono, {AGUA: 1}))

    r = outro.post("atualizar_status_pedido.php", {"numero_do_pedido": numero, "acao": "cancelar", "voltar": "meus_pedidos.php"})
    assert "não pode mais ser alterado" in r.text
    assert db.valor("SELECT status FROM pedido WHERE numero_do_pedido = %s", numero) == "Aguardando aceitação"
    assert f">{numero}<" not in outro.get("meus_pedidos.php").text


def test_cliente_nao_executa_acoes_de_funcionario(db):
    cliente = Navegador()
    novo_cliente(cliente)
    numero = numero_do_pedido(fazer_pedido(cliente, {AGUA: 1}))
    r = cliente.post("atualizar_status_pedido.php", {"numero_do_pedido": numero, "acao": "aceitar", "voltar": "meus_pedidos.php"})
    assert "Ação não permitida" in r.text
    assert db.valor("SELECT status FROM pedido WHERE numero_do_pedido = %s", numero) == "Aguardando aceitação"

    r = cliente.post("atualizar_status_pedido.php", {"numero_do_pedido": numero, "acao": "cancelar", "voltar": "meus_pedidos.php"})
    assert "cancelado" in r.text
    assert db.valor("SELECT status FROM pedido WHERE numero_do_pedido = %s", numero) == "Cancelado/recusado"


def test_observacao_com_html_e_exibida_como_texto(balconista):
    cliente = Navegador()
    novo_cliente(cliente)
    numero_do_pedido(fazer_pedido(cliente, {AGUA: 1}, {AGUA: "<script>alert(1)</script>"}))
    pagina = balconista.get("pedidos.php").text
    assert "&lt;script&gt;alert(1)&lt;/script&gt;" in pagina
    assert "<script>alert(1)</script>" not in pagina


# ------------------------------------------------------------------ Cardápio, estoque e relatório

def png_minimo():
    """Imagem PNG 1x1 válida."""
    def bloco(tipo, dados):
        return struct.pack(">I", len(dados)) + tipo + dados + struct.pack(">I", zlib.crc32(tipo + dados) & 0xffffffff)
    return (b"\x89PNG\r\n\x1a\n" + bloco(b"IHDR", struct.pack(">IIBBBBB", 1, 1, 8, 2, 0, 0, 0))
            + bloco(b"IDAT", zlib.compress(b"\x00\xc0\x39\x2b")) + bloco(b"IEND", b""))


def test_cadastro_de_item_com_receita_e_imagem(balconista, db):
    nome = "Burger Teste " + uuid.uuid4().hex[:6]
    dados = {"nome": nome, "descricao": "Item de teste", "categoria": 1, "preco": "19,90",
             f"ingredientes[{PAO}]": 1, f"ingredientes[{HAMBURGUER}]": 1, "csrf": balconista.token()}
    r = balconista.sessao.post(balconista.url("adiciona_item_cardapio.php"), data=dados,
                               files={"imagem": ("burger.png", png_minimo(), "image/png")})
    assert "Item adicionado ao cardápio" in r.text, r.text[:1500]
    item = db.um("SELECT * FROM cardapio WHERE nome = %s", nome)
    assert float(item["preco"]) == 19.9
    assert re.fullmatch(r"[0-9a-f]{16}\.png", item["imagem"])
    assert db.valor("SELECT COUNT(*) FROM cardapio_ingrediente WHERE id_cardapio = %s", item["id"]) == 2
    assert balconista.sessao.get(balconista.url("imagens/lanches/" + item["imagem"])).status_code == 200
    assert nome in Navegador().get("cardapio_cliente.php").text


def test_upload_de_arquivo_que_nao_e_imagem_e_recusado(balconista, db):
    nome = "Item Malicioso " + uuid.uuid4().hex[:6]
    dados = {"nome": nome, "categoria": 1, "preco": "10", "csrf": balconista.token()}
    r = balconista.sessao.post(balconista.url("adiciona_item_cardapio.php"), data=dados,
                               files={"imagem": ("foto.png", b"<?php echo 'invadido'; ?>", "image/png")})
    assert "Envie uma imagem PNG, JPG ou WebP" in r.text
    assert db.valor("SELECT COUNT(*) FROM cardapio WHERE nome = %s", nome) == 0


def test_adicionar_ingrediente_existente_soma_quantidade(balconista, db):
    antes = db.valor("SELECT quantidade FROM estoque WHERE id_ingrediente = %s", BATATA)
    balconista.post("adiciona_ingrediente.php", {"nome_ingrediente": "Batata frita", "quantidade": 10})
    assert db.valor("SELECT quantidade FROM estoque WHERE id_ingrediente = %s", BATATA) == antes + 10
    r = balconista.post("adiciona_ingrediente.php", {"nome_ingrediente": "Batata frita", "quantidade": -5})
    assert "quantidade entre 1 e" in r.text


def test_relatorio_ignora_pedidos_cancelados(balconista, db):
    esperado = db.valor("SELECT COALESCE(SUM(valor), 0) FROM pedido WHERE status <> 'Cancelado/recusado'")
    texto = f"R$ {float(esperado):,.2f}".replace(",", "X").replace(".", ",").replace("X", ".")
    assert texto in balconista.get("relatorio.php").text


def test_nao_exclui_o_ultimo_balconista(balconista, db):
    if db.valor("SELECT COUNT(*) FROM balconista_dono") == 1:
        r = balconista.post("delete_balconista_dono.php", {"cpf": CPF_BALCONISTA})
        assert "último balconista" in r.text
        assert db.valor("SELECT COUNT(*) FROM balconista_dono") == 1


def test_muitas_tentativas_de_login_bloqueiam_temporariamente():
    navegador = Navegador()
    for _ in range(5):
        navegador.post("valida_login_cozinheiro.php", {"cpf": CPF_COZINHEIRO, "senha": "errada"})
    r = navegador.post("valida_login_cozinheiro.php", {"cpf": CPF_COZINHEIRO, "senha": SENHA_DEMO})
    assert "Muitas tentativas" in r.text
