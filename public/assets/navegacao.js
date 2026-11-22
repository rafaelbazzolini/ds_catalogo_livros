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
