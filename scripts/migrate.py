#!/usr/bin/env python3
import json
import subprocess
from pathlib import Path

from banco import carregar_sql

docker = '/Applications/Docker.app/Contents/Resources/bin/docker'
if not Path(docker).exists():
    docker = 'docker'
configuracao = json.loads(subprocess.check_output([docker, 'inspect', 'mariadb']))[0]
ambiente = dict(item.split('=', 1) for item in configuracao['Config']['Env'] if '=' in item)
senha = ambiente.get('MARIADB_ROOT_PASSWORD') or ambiente.get('MYSQL_ROOT_PASSWORD')
if not senha:
    raise SystemExit('Execute os scripts da pasta sql, conforme sql/ordem.txt, manualmente com uma conta administrativa.')
sql = carregar_sql()
subprocess.run([docker, 'exec', '-i', '-e', 'MYSQL_PWD='+senha, 'mariadb', 'mariadb', '-uroot', 'catalogo_livros'], input=sql.encode(), check=True)
print('Scripts de banco aplicados ao catalogo_livros.')
