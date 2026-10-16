"""Gera as capturas de tela do README abrindo o sistema em um navegador (Playwright).

Uso: python tests/capturas.py <pasta-de-saida>
Deve rodar com o banco recém-criado (dados de exemplo), antes dos testes.
"""

import os
import sys
from pathlib import Path

from playwright.sync_api import sync_playwright

URL = os.environ.get("URL_BASE", "http://localhost:8080")
SAIDA = Path(sys.argv[1] if len(sys.argv) > 1 else "docs/img")


def entrar(pagina, tela, campos):
    pagina.goto(f"{URL}/{tela}")
    for nome, valor in campos.items():
        pagina.fill(f"input[name={nome}]", valor)
    pagina.click("button[type=submit]")
    pagina.wait_for_load_state("networkidle")


def capturar(pagina, arquivo, pagina_inteira=False):
    pagina.wait_for_load_state("networkidle")
    pagina.screenshot(path=str(SAIDA / arquivo), full_page=pagina_inteira)
    print("  ", arquivo)


def main():
    SAIDA.mkdir(parents=True, exist_ok=True)
    with sync_playwright() as p:
        navegador = p.chromium.launch()
        opcoes = {"viewport": {"width": 1280, "height": 800}, "locale": "pt-BR"}

        # Cliente
        cliente = navegador.new_context(**opcoes).new_page()
        cliente.goto(f"{URL}/login.php")
        capturar(cliente, "login.png")
        entrar(cliente, "login.php", {"login": "joao", "senha": "123456"})
        cliente.goto(f"{URL}/cardapio_cliente.php")
        capturar(cliente, "cardapio-cliente.png")
        cliente.fill("input[name='quantidade[1]']", "2")
        cliente.fill("input[name='quantidade[4]']", "1")
        cliente.click("text=Adicionar ao carrinho")
        cliente.goto(f"{URL}/carrinho.php")
        cliente.fill("input[name='observacao[1]']", "Sem tomate")
        capturar(cliente, "carrinho.png")
        cliente.goto(f"{URL}/pagamento.php")
        capturar(cliente, "pagamento.png", pagina_inteira=True)
        cliente.goto(f"{URL}/meus_pedidos.php")
        capturar(cliente, "meus-pedidos.png")

        # Balconista / dono
        balcao = navegador.new_context(**opcoes).new_page()
        entrar(balcao, "login_balconista.php", {"cpf": "352.658.110-01", "senha": "123456"})
        capturar(balcao, "pedidos-aguardando.png")
        balcao.goto(f"{URL}/pedidos.php?visao=todos")
        capturar(balcao, "pedidos-todos.png")
        balcao.goto(f"{URL}/cardapio.php")
        capturar(balcao, "cardapio-balconista.png")
        balcao.goto(f"{URL}/editar_cardapio.php?id=1")
        capturar(balcao, "editar-item.png", pagina_inteira=True)
        balcao.goto(f"{URL}/estoque.php")
        capturar(balcao, "estoque.png")
        balcao.goto(f"{URL}/relatorio.php")
        capturar(balcao, "relatorio.png", pagina_inteira=True)

        # Cozinheiro
        cozinha = navegador.new_context(**opcoes).new_page()
        entrar(cozinha, "login_cozinheiro.php", {"cpf": "211.996.250-27", "senha": "123456"})
        capturar(cozinha, "cozinha.png")

        # Celular
        celular = navegador.new_context(viewport={"width": 390, "height": 844}, locale="pt-BR", device_scale_factor=2).new_page()
        celular.goto(f"{URL}/cardapio_cliente.php")
        capturar(celular, "celular-cardapio.png")

        navegador.close()


if __name__ == "__main__":
    main()
