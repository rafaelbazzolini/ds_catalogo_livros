#!/usr/bin/env python3
import json
import secrets
import subprocess
from pathlib import Path

from banco import carregar_sql

docker = '/Applications/Docker.app/Contents/Resources/bin/docker'
if not Path(docker).exists():
    docker = 'docker'
container = 'mariadb'
configuracao = json.loads(subprocess.check_output([docker, 'inspect', container]))[0]
ambiente = dict(item.split('=', 1) for item in configuracao['Config']['Env'] if '=' in item)
senha_administrativa = ambiente.get('MARIADB_ROOT_PASSWORD') or ambiente.get('MYSQL_ROOT_PASSWORD')
if not senha_administrativa:
    raise SystemExit('Senha administrativa não encontrada no ambiente do container. Configure o banco manualmente conforme o README.')
arquivo_ambiente = Path('.env.local')
if arquivo_ambiente.exists():
    raise SystemExit('.env.local já existe. Para reaplicar os scripts de banco, use python3 scripts/migrate.py.')
senha = secrets.token_hex(24)
sql = f"CREATE DATABASE IF NOT EXISTS catalogo_livros CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE USER IF NOT EXISTS 'catalogo_app'@'%' IDENTIFIED BY '{senha}'; GRANT SELECT, INSERT, UPDATE, DELETE ON catalogo_livros.* TO 'catalogo_app'@'%'; USE catalogo_livros;\n"
sql += carregar_sql()
subprocess.run([docker, 'exec', '-i', '-e', 'MYSQL_PWD='+senha_administrativa, container, 'mariadb', '-uroot'], input=sql.encode(), check=True)
arquivo_ambiente.write_text(
    '# Configuração local; não versionar.\n'
    f'APP_SECRET={secrets.token_hex(32)}\n\n'
    'DB_HOST=mariadb\n'
    'DB_PORT=3306\n'
    'DB_DATABASE=catalogo_livros\n'
    'DB_USERNAME=catalogo_app\n'
    f'DB_PASSWORD={senha}\n'
    'DB_CHARSET=utf8mb4\n' 
)
arquivo_ambiente.chmod(0o600)
print('Banco, usuário restrito e schema configurados. Credenciais salvas em .env.local.')
