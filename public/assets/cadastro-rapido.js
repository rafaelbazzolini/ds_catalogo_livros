const janelaCadastro = document.querySelector('#quick-create');

if (janelaCadastro) {
    const formulario = janelaCadastro.querySelector('form');
    const entrada = janelaCadastro.querySelector('#quick-create-value');
    const erro = janelaCadastro.querySelector('#quick-create-error');
    let botaoOrigem;
    let salvando = false;

    document.querySelectorAll('[data-quick-create]').forEach((botao) => {
        botao.addEventListener('click', (evento) => {
            evento.preventDefault();
            botaoOrigem = botao;
            formulario.reset();
            erro.hidden = true;
            janelaCadastro.querySelector('h2').textContent = botao.dataset.title;
            janelaCadastro.querySelector('#quick-create-label').textContent = botao.dataset.label;
            entrada.maxLength = Number(botao.dataset.max);
            janelaCadastro.showModal();
            entrada.focus();
        });
    });

    janelaCadastro.querySelectorAll('[data-dialog-close]').forEach((botao) => {
        botao.addEventListener('click', () => {
            if (!salvando) janelaCadastro.close();
        });
    });
    janelaCadastro.addEventListener('cancel', (evento) => {
        if (salvando) evento.preventDefault();
    });
    janelaCadastro.addEventListener('close', () => botaoOrigem?.focus());

    formulario.addEventListener('submit', async (evento) => {
        evento.preventDefault();
        if (salvando) return;
        salvando = true;
        erro.hidden = true;
        formulario.querySelectorAll('button').forEach((botao) => botao.disabled = true);
        const botaoSalvar = formulario.querySelector('[type=submit]');
        botaoSalvar.textContent = 'Salvando…';
        try {
            const resposta = await fetch(botaoOrigem.dataset.url, {
                method: 'POST',
                headers: {'Accept': 'application/json'},
                body: new URLSearchParams({[botaoOrigem.dataset.field]: entrada.value, _token: botaoOrigem.dataset.token}),
            });
            const resultado = resposta.headers.get('content-type')?.includes('application/json') ? await resposta.json() : null;
            if (!resposta.ok) {
                erro.textContent = resultado?.erros?.join(' ') || 'Não foi possível salvar. Tente novamente em instantes.';
                erro.hidden = false;
                return;
            }
            const tipo = botaoOrigem.dataset.quickCreate;
            const seletorRelacionamento = document.querySelector(`[data-relation="${tipo}"]`);
            seletorRelacionamento.dispatchEvent(new CustomEvent('relation:add', {detail: resultado.items[0]}));
            janelaCadastro.close();
        } catch {
            erro.textContent = 'Não foi possível confirmar o cadastro. Verifique sua conexão e confira se o item foi criado antes de tentar novamente.';
            erro.hidden = false;
        } finally {
            salvando = false;
            formulario.querySelectorAll('button').forEach((botao) => botao.disabled = false);
            botaoSalvar.textContent = 'Salvar';
        }
    });
}
