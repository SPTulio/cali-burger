"""Infraestrutura dos testes de ponta a ponta.

Os testes usam o sistema de verdade, rodando no Docker (docker compose up):
fazem requisições HTTP como um navegador faria e conferem o resultado
direto no banco de dados.
"""

import os
import re
import uuid

import pymysql
import pytest
import requests

URL_BASE = os.environ.get("URL_BASE", "http://localhost:8080")

SENHA_DEMO = "123456"
CPF_BALCONISTA = "35265811001"
CPF_COZINHEIRO = "21199625027"

AVISOS_PHP = re.compile(r"(Warning|Notice|Deprecated|Fatal error|Parse error|Uncaught)\b.*?(on line|in /)", re.S)


class Navegador:
    """Sessão HTTP que guarda cookies e envia o token CSRF automaticamente."""

    def __init__(self):
        self.sessao = requests.Session()
        self.ultima = None

    def url(self, caminho):
        return f"{URL_BASE}/{caminho.lstrip('/')}"

    def get(self, caminho, **kwargs):
        self.ultima = self.sessao.get(self.url(caminho), timeout=20, **kwargs)
        verificar_sem_avisos_php(self.ultima)
        return self.ultima

    def token(self):
        pagina = self.sessao.get(self.url("login.php"), timeout=20).text
        achado = re.search(r'name="csrf" value="([0-9a-f]+)"', pagina) or re.search(
            r'name="csrf-token" content="([0-9a-f]+)"', pagina)
        assert achado, "token CSRF não encontrado"
        return achado.group(1)

    def post(self, caminho, dados=None, com_csrf=True, **kwargs):
        dados = dict(dados or {})
        if com_csrf:
            dados["csrf"] = self.token()
        self.ultima = self.sessao.post(self.url(caminho), data=dados, timeout=20, **kwargs)
        verificar_sem_avisos_php(self.ultima)
        return self.ultima

    def entrar_cliente(self, login="joao", senha=SENHA_DEMO):
        r = self.post("valida_login.php", {"login": login, "senha": senha})
        assert "cardapio_cliente.php" in r.url or "Sair" in r.text, f"login de cliente falhou: {login}"
        return self

    def entrar_funcionario(self, perfil, cpf, senha=SENHA_DEMO):
        r = self.post(f"valida_login_{perfil}.php", {"cpf": cpf, "senha": senha})
        assert "Sair" in r.text, f"login de {perfil} falhou: {cpf}"
        return self


def verificar_sem_avisos_php(resposta):
    achado = AVISOS_PHP.search(resposta.text)
    assert not achado, f"Aviso do PHP em {resposta.url}: {achado.group(0)[:300]}"


@pytest.fixture
def navegador():
    return Navegador()


@pytest.fixture
def cliente():
    return Navegador().entrar_cliente()


@pytest.fixture
def balconista():
    return Navegador().entrar_funcionario("balconista", CPF_BALCONISTA)


@pytest.fixture
def cozinheiro():
    return Navegador().entrar_funcionario("cozinheiro", CPF_COZINHEIRO)


@pytest.fixture(scope="session")
def db():
    conexao = pymysql.connect(
        host=os.environ.get("DB_HOST", "127.0.0.1"),
        port=int(os.environ.get("DB_PORTA", "3307")),
        user=os.environ.get("DB_USUARIO", "caliburger"),
        password=os.environ.get("DB_SENHA", "caliburger"),
        database="db_caliburger",
        autocommit=True,
        cursorclass=pymysql.cursors.DictCursor,
        charset="utf8mb4",
    )
    yield Banco(conexao)
    conexao.close()


class Banco:
    def __init__(self, conexao):
        self.conexao = conexao

    def todos(self, sql, *parametros):
        with self.conexao.cursor() as c:
            c.execute(sql, parametros)
            return c.fetchall()

    def um(self, sql, *parametros):
        linhas = self.todos(sql, *parametros)
        return linhas[0] if linhas else None

    def valor(self, sql, *parametros):
        linha = self.um(sql, *parametros)
        return None if linha is None else next(iter(linha.values()))

    def executar(self, sql, *parametros):
        with self.conexao.cursor() as c:
            c.execute(sql, parametros)

    def estoque(self):
        return {l["id_ingrediente"]: l["quantidade"] for l in self.todos("SELECT id_ingrediente, quantidade FROM estoque")}


def cpf_valido():
    """Gera um CPF fictício que passa na validação dos dígitos verificadores."""
    base = [int(d) for d in str(uuid.uuid4().int)[:9]]
    for t in (9, 10):
        soma = sum(d * (t + 1 - i) for i, d in enumerate(base[:t]))
        base.append((soma * 10 % 11) % 10)
    cpf = "".join(map(str, base))
    return cpf if len(set(cpf)) > 1 else cpf_valido()


def novo_cliente(navegador, nome="Cliente de Teste"):
    """Cadastra um cliente novo e faz login com ele. Retorna (login, cpf)."""
    login = "teste" + uuid.uuid4().hex[:10]
    cpf = cpf_valido()
    r = navegador.post("cadastro.php", {
        "login": login, "senha": "segredo123", "confirmar_senha": "segredo123", "nome": nome,
        "email": f"{login}@exemplo.com", "cpf": cpf, "data_nascimento": "1999-09-09",
        "estado": "PR", "cidade": "Curitiba", "bairro": "Centro", "logradouro": "Rua dos Testes, 1",
    })
    assert "Cadastro realizado com sucesso" in r.text, r.text[:2000]
    navegador.entrar_cliente(login, "segredo123")
    return login, cpf


def fazer_pedido(navegador, itens, observacoes=None):
    """Coloca itens no carrinho ({id: qtd}) e confirma o pagamento. Retorna a resposta final."""
    navegador.post("carrinho.php", {"acao": "adicionar", **{f"quantidade[{i}]": q for i, q in itens.items()}})
    if observacoes:
        dados = {"acao": "atualizar"}
        for i, q in itens.items():
            dados[f"quantidade[{i}]"] = q
            dados[f"observacao[{i}]"] = observacoes.get(i, "")
        navegador.post("carrinho.php", dados)
    return navegador.post("pedido_sucesso.php", {"confirmar_pagamento": "1"})


def numero_do_pedido(resposta):
    achado = re.search(r"pedido=(\d+)", resposta.url)
    assert achado, f"pedido não foi criado: {resposta.url}"
    return int(achado.group(1))
