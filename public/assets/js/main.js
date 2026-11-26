document.querySelectorAll('select.form-select:not([multiple])').forEach((seletor) => {
    const envoltorio = document.createElement('div');
    envoltorio.className = 'select-control';
    seletor.before(envoltorio);
    envoltorio.append(seletor);

    const rotulo = seletor.labels[0];
    const botao = document.createElement('button');
    botao.type = 'button';
    botao.className = 'select-toggle';
    botao.id = `${seletor.id}-toggle`;
    botao.setAttribute('role', 'combobox');
    botao.setAttribute('aria-haspopup', 'listbox');
    botao.setAttribute('aria-expanded', 'false');
    botao.setAttribute('aria-controls', `${seletor.id}-options`);
    if (rotulo) {
        rotulo.id ||= `${seletor.id}-label`;
        rotulo.htmlFor = botao.id;
        botao.setAttribute('aria-labelledby', rotulo.id);
    }
    botao.setAttribute('aria-required', String(seletor.required));
    botao.disabled = seletor.disabled;

    const menu = document.createElement('div');
    menu.className = 'select-menu';
    menu.id = `${seletor.id}-options`;
    menu.setAttribute('role', 'listbox');
    if (rotulo) menu.setAttribute('aria-labelledby', rotulo.id);
    menu.hidden = true;
    let ativo = seletor.selectedIndex;

    const opcoes = Array.from(seletor.options, (opcao, indice) => {
        const item = document.createElement('div');
        item.className = 'select-option';
        item.id = `${seletor.id}-option-${indice}`;
        item.setAttribute('role', 'option');
        item.textContent = opcao.textContent;
        item.addEventListener('mousedown', (evento) => evento.preventDefault());
        item.addEventListener('click', () => escolher(indice));
        menu.append(item);
        return item;
    });

    function sincronizar() {
        botao.textContent = seletor.options[seletor.selectedIndex]?.textContent ?? '';
        opcoes.forEach((item, indice) => item.setAttribute('aria-selected', String(indice === seletor.selectedIndex)));
    }

    function destacar(indice) {
        ativo = Math.max(0, Math.min(opcoes.length - 1, indice));
        opcoes.forEach((item, i) => item.classList.toggle('is-focused', i === ativo));
        botao.setAttribute('aria-activedescendant', opcoes[ativo].id);
        opcoes[ativo].scrollIntoView({block: 'nearest'});
    }

    function fechar() {
        menu.hidden = true;
        botao.setAttribute('aria-expanded', 'false');
        botao.removeAttribute('aria-activedescendant');
    }

    function abrir() {
        menu.hidden = false;
        botao.setAttribute('aria-expanded', 'true');
        destacar(seletor.selectedIndex);
    }

    function escolher(indice) {
        const alterado = seletor.selectedIndex !== indice;
        seletor.selectedIndex = indice;
        sincronizar();
        fechar();
        botao.focus();
        if (alterado) seletor.dispatchEvent(new Event('change', {bubbles: true}));
    }

    botao.addEventListener('click', () => menu.hidden ? abrir() : fechar());
    botao.addEventListener('keydown', (evento) => {
        if (evento.key === 'Tab' || evento.key === 'Escape') {
            fechar();
            return;
        }
        if (['ArrowDown', 'ArrowUp', 'Home', 'End', 'Enter', ' '].includes(evento.key)) {
            evento.preventDefault();
            if (menu.hidden) {
                abrir();
            } else if (evento.key === 'Enter' || evento.key === ' ') {
                escolher(ativo);
            } else {
                destacar(evento.key === 'Home' ? 0 : evento.key === 'End' ? opcoes.length - 1 : ativo + (evento.key === 'ArrowDown' ? 1 : -1));
            }
        }
    });
    envoltorio.addEventListener('focusout', (evento) => {
        if (!envoltorio.contains(evento.relatedTarget)) fechar();
    });
    document.addEventListener('click', (evento) => {
        if (!envoltorio.contains(evento.target)) fechar();
    });
    seletor.addEventListener('change', sincronizar);
    seletor.form?.addEventListener('reset', () => setTimeout(sincronizar, 0));
    seletor.classList.add('select-native');
    seletor.setAttribute('aria-hidden', 'true');
    seletor.tabIndex = -1;
    envoltorio.append(botao, menu);
    sincronizar();
});

const botaoMenu = document.querySelector('.menu-toggle');
const fundoMenu = document.querySelector('.sidebar-overlay');
const menuLateral = document.querySelector('.sidebar');
const menuMovel = window.matchMedia('(max-width: 767px)');

function definirMenu(abrir) {
    document.body.classList.toggle('sidebar-open', abrir);
    botaoMenu.setAttribute('aria-expanded', String(abrir));
    botaoMenu.setAttribute('aria-label', abrir ? 'Fechar menu' : 'Abrir menu');
    fundoMenu.hidden = !abrir;
    menuLateral.inert = menuMovel.matches && !abrir;
    if (abrir) document.querySelector('.sidebar-link')?.focus();
}

botaoMenu?.addEventListener('click', () => definirMenu(botaoMenu.getAttribute('aria-expanded') !== 'true'));
fundoMenu?.addEventListener('click', () => definirMenu(false));
document.addEventListener('keydown', (evento) => {
    if (evento.key === 'Escape' && document.body.classList.contains('sidebar-open')) {
        definirMenu(false);
        botaoMenu.focus();
    }
});

menuMovel.addEventListener('change', () => definirMenu(false));
if (menuLateral) menuLateral.inert = menuMovel.matches;

document.querySelectorAll('[data-relation]').forEach((secao) => {
    const tipo = secao.dataset.relation;
    const etiquetas = secao.querySelector('.selected-relations');
    const entrada = secao.querySelector('[role=combobox]');
    const listaSuspensa = secao.querySelector('.relation-dropdown');
    const resultados = secao.querySelector('.relation-results');
    const retorno = secao.querySelector('.relation-feedback');
    const botaoMais = secao.querySelector('.relation-more');
    const selecionados = new Map(Array.from(etiquetas.querySelectorAll('.selected-item'), (item) => [item.dataset.id, item.dataset.label]));
    let itens = [];
    let pagina = 1;
    let ativo = -1;
    let controlador;
    let temporizador;
    let sequencia = 0;

    function fechar() {
        listaSuspensa.hidden = true;
        entrada.setAttribute('aria-expanded', 'false');
        entrada.removeAttribute('aria-activedescendant');
    }

    function renderizarSelecionados() {
        etiquetas.replaceChildren();
        selecionados.forEach((rotulo, id) => {
            const etiqueta = document.createElement('span');
            etiqueta.className = 'selected-item';
            const oculto = document.createElement('input');
            oculto.type = 'hidden';
            oculto.name = `${tipo}[]`;
            oculto.value = id;
            const texto = document.createElement('span');
            texto.textContent = rotulo;
            const remover = document.createElement('button');
            remover.type = 'button';
            remover.textContent = '×';
            remover.setAttribute('aria-label', `Remover ${rotulo}`);
            remover.addEventListener('click', () => {
                selecionados.delete(id);
                renderizarSelecionados();
                renderizarResultados();
                entrada.focus();
            });
            etiqueta.append(oculto, texto, remover);
            etiquetas.append(etiqueta);
        });
        secao.querySelector('.relation-count').textContent = selecionados.size;
        entrada.setCustomValidity('');
    }

    function alternar(item) {
        const id = String(item.id);
        if (selecionados.has(id)) selecionados.delete(id);
        else selecionados.set(id, item.label);
        renderizarSelecionados();
        renderizarResultados();
        entrada.focus();
    }

    function renderizarResultados() {
        resultados.replaceChildren();
        itens.forEach((item, indice) => {
            const opcao = document.createElement('div');
            opcao.className = 'relation-option';
            opcao.id = `${tipo}-result-${item.id}`;
            opcao.setAttribute('role', 'option');
            opcao.setAttribute('aria-selected', String(selecionados.has(String(item.id))));
            opcao.textContent = item.label;
            opcao.classList.toggle('is-focused', indice === ativo);
            opcao.addEventListener('mousedown', (evento) => evento.preventDefault());
            opcao.addEventListener('click', () => alternar(item));
            resultados.append(opcao);
        });
        entrada.removeAttribute('aria-activedescendant');
        if (itens[ativo]) {
            entrada.setAttribute('aria-activedescendant', `${tipo}-result-${itens[ativo].id}`);
            resultados.children[ativo].scrollIntoView({block: 'nearest'});
        }
    }

    async function carregar(acrescentar = false) {
        controlador?.abort();
        controlador = new AbortController();
        const idRequisicao = ++sequencia;
        const proximaPagina = acrescentar ? pagina + 1 : 1;
        if (!acrescentar) { itens = []; ativo = -1; renderizarResultados(); }
        listaSuspensa.hidden = false;
        entrada.setAttribute('aria-expanded', 'true');
        resultados.setAttribute('aria-busy', 'true');
        retorno.textContent = 'Carregando…';
        botaoMais.hidden = true;
        try {
            const endereco = new URL(secao.dataset.searchUrl, location.origin);
            endereco.searchParams.set('q', entrada.value.trim());
            endereco.searchParams.set('pagina', proximaPagina);
            const resposta = await fetch(endereco, {signal: controlador.signal, headers: {'Accept': 'application/json'}});
            if (!resposta.ok) throw new Error('search');
            const dados = await resposta.json();
            if (idRequisicao !== sequencia) return;
            pagina = proximaPagina;
            itens = acrescentar ? [...itens, ...dados.items.filter((item) => !itens.some((existente) => existente.id === item.id))] : dados.items;
            renderizarResultados();
            retorno.textContent = itens.length ? '' : 'Nenhum resultado encontrado.';
            botaoMais.textContent = 'Carregar mais';
            botaoMais.hidden = !dados.hasMore;
            botaoMais.onclick = () => carregar(true);
        } catch (erro) {
            if (erro.name === 'AbortError' || idRequisicao !== sequencia) return;
            retorno.textContent = 'Não foi possível carregar os itens.';
            botaoMais.textContent = 'Tentar novamente';
            botaoMais.hidden = false;
            botaoMais.onclick = () => carregar(acrescentar);
        } finally {
            if (idRequisicao === sequencia) resultados.setAttribute('aria-busy', 'false');
        }
    }

    entrada.addEventListener('focus', () => carregar());
    entrada.addEventListener('click', () => {
        if (listaSuspensa.hidden) carregar();
    });
    entrada.addEventListener('input', () => {
        clearTimeout(temporizador);
        controlador?.abort();
        sequencia++;
        ativo = -1;
        itens = [];
        renderizarResultados();
        retorno.textContent = 'Pesquisando…';
        botaoMais.hidden = true;
        listaSuspensa.hidden = false;
        entrada.setAttribute('aria-expanded', 'true');
        temporizador = setTimeout(() => carregar(), 250);
    });
    entrada.addEventListener('keydown', (evento) => {
        if (evento.key === 'Escape') { evento.preventDefault(); fechar(); }
        if (evento.key === 'ArrowDown' || evento.key === 'ArrowUp') {
            evento.preventDefault();
            if (listaSuspensa.hidden) { carregar(); return; }
            ativo = Math.max(0, Math.min(itens.length - 1, ativo + (evento.key === 'ArrowDown' ? 1 : -1)));
            renderizarResultados();
        }
        if (evento.key === 'Enter') {
            evento.preventDefault();
            if (!listaSuspensa.hidden && itens[ativo]) alternar(itens[ativo]);
        }
    });
    secao.addEventListener('focusout', (evento) => {
        if (!secao.contains(evento.relatedTarget)) fechar();
    });
    document.addEventListener('click', (evento) => {
        if (!evento.composedPath().includes(secao)) fechar();
    });
    secao.addEventListener('toggle', () => { if (!secao.open) fechar(); });
    secao.addEventListener('relation:add', (evento) => {
        selecionados.set(String(evento.detail.id), evento.detail.label);
        secao.open = true;
        renderizarSelecionados();
        fechar();
    });
    secao.closest('form').addEventListener('submit', (evento) => {
        if (selecionados.size || evento.defaultPrevented) return;
        evento.preventDefault();
        secao.open = true;
        entrada.setCustomValidity(`Selecione pelo menos um item em ${tipo}.`);
        entrada.reportValidity();
    });
    renderizarSelecionados();
});

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

document.querySelectorAll('[data-moeda]').forEach((campo) => {
    campo.addEventListener('input', () => {
        const cursor = campo.selectionStart ?? campo.value.length;
        const digitosDepois = campo.value.slice(cursor).replace(/\D/g, '').length;
        const digitos = campo.value.replace(/\D/g, '');

        if (!digitos) {
            campo.value = '';
            return;
        }

        // Os dois últimos dígitos representam os centavos.
        const valor = digitos.replace(/^0+/, '').padStart(3, '0');
        const inteiro = valor.slice(0, -2).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        campo.value = `${inteiro},${valor.slice(-2)}`;

        let posicao = campo.value.length;
        let restantes = digitosDepois;
        while (posicao > 0 && restantes > 0) {
            posicao -= 1;
            if (/\d/.test(campo.value[posicao])) restantes -= 1;
        }
        campo.setSelectionRange(posicao, posicao);
    });
});

document.querySelectorAll('[data-notificacao]').forEach((notificacao) => {
    setTimeout(() => notificacao.remove(), 5000);
});

document.querySelectorAll('[data-ano]').forEach((campo) => {
    const anoAtual = Number(new Intl.DateTimeFormat('en', {year: 'numeric', timeZone: 'America/Sao_Paulo'}).format(new Date()));
    function validarAno() {
        campo.setCustomValidity(Number(campo.value) > anoAtual ? 'O ano de publicação não pode ser maior que o ano atual.' : '');
    }
    campo.addEventListener('input', () => {
        campo.value = campo.value.replace(/\D/g, '').slice(0, 4);
        validarAno();
    });
    validarAno();

    const envoltorio = campo.closest('.campo-ano');
    if (!envoltorio) return;
    const botao = envoltorio.querySelector('.abrir-anos');
    const calendario = envoltorio.querySelector('.calendario-anos');
    const lista = calendario.querySelector('.lista-anos');
    let decada;

    function fecharCalendario() {
        calendario.hidden = true;
        botao.setAttribute('aria-expanded', 'false');
    }

    function mostrarAnos() {
        calendario.querySelector('span').textContent = `${decada} – ${decada + 9}`;
        lista.replaceChildren();
        for (let ano = decada; ano <= decada + 9; ano += 1) {
            const opcao = document.createElement('button');
            opcao.type = 'button';
            opcao.textContent = ano;
            opcao.disabled = ano > anoAtual;
            opcao.setAttribute('aria-pressed', String(ano === Number(campo.value)));
            opcao.addEventListener('click', () => {
                campo.value = ano;
                campo.dispatchEvent(new Event('input', {bubbles: true}));
                campo.dispatchEvent(new Event('change', {bubbles: true}));
                fecharCalendario();
                campo.focus();
            });
            lista.append(opcao);
        }
        calendario.querySelector('[data-decada="-1"]').disabled = decada <= 1000;
        calendario.querySelector('[data-decada="1"]').disabled = decada + 10 > anoAtual;
    }

    botao.addEventListener('click', () => {
        if (!calendario.hidden) {
            fecharCalendario();
            return;
        }
        const ano = /^[1-9]\d{3}$/.test(campo.value) ? Math.min(Number(campo.value), anoAtual) : anoAtual;
        decada = Math.floor(ano / 10) * 10;
        mostrarAnos();
        calendario.hidden = false;
        botao.setAttribute('aria-expanded', 'true');
        lista.querySelector('[aria-pressed="true"]')?.focus();
    });

    calendario.querySelectorAll('[data-decada]').forEach((controle) => {
        controle.addEventListener('click', () => {
            decada += Number(controle.dataset.decada) * 10;
            mostrarAnos();
        });
    });
    document.addEventListener('click', (evento) => {
        if (!envoltorio.contains(evento.target)) fecharCalendario();
    });
    envoltorio.addEventListener('focusout', (evento) => {
        if (!envoltorio.contains(evento.relatedTarget)) fecharCalendario();
    });
    envoltorio.addEventListener('keydown', (evento) => {
        if (evento.key === 'Escape') {
            fecharCalendario();
            botao.focus();
        }
    });
});
