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
