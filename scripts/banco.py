from pathlib import Path


def carregar_sql() -> str:
    # Respeita as dependências entre tabelas e a view.
    pasta = Path(__file__).resolve().parent.parent / 'sql'
    arquivos = (pasta / 'ordem.txt').read_text(encoding='utf-8').splitlines()
    return '\n'.join(
        (pasta / nome.strip()).read_text(encoding='utf-8')
        for nome in arquivos
        if nome.strip()
    )
