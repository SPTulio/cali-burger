// Janela de confirmação para formulários com o atributo data-confirmar.
// Ex.: <form data-confirmar="Deseja excluir este item?"> ... </form>
(function () {
    const modal = document.getElementById('modal-confirmacao');
    if (!modal) {
        return;
    }
    const texto = document.getElementById('modal-texto');
    const botaoConfirmar = document.getElementById('modal-confirmar');
    const botaoCancelar = document.getElementById('modal-cancelar');
    let formulario = null;

    function fechar() {
        modal.hidden = true;
        formulario = null;
    }

    document.addEventListener('submit', function (evento) {
        const form = evento.target;
        if (!(form instanceof HTMLFormElement) || !form.dataset.confirmar) {
            return;
        }
        evento.preventDefault();
        formulario = form;
        texto.textContent = form.dataset.confirmar;
        modal.hidden = false;
        botaoConfirmar.focus();
    });

    botaoConfirmar.addEventListener('click', function () {
        if (formulario) {
            const form = formulario;
            fechar();
            form.submit();
        }
    });
    botaoCancelar.addEventListener('click', fechar);
    modal.addEventListener('click', function (evento) {
        if (evento.target === modal) {
            fechar();
        }
    });
    document.addEventListener('keydown', function (evento) {
        if (evento.key === 'Escape' && !modal.hidden) {
            fechar();
        }
    });
})();
