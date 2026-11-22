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
            itens = acrescentar ? [...items, ...data.items.filter((item) => !itens.some((existente) => existente.id === item.id))] : dados.items;
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
        if (!secao.contains(evento.target)) fechar();
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
