# Comandos que utilizei para o meu ambiente

Roteiro do projeto `teste`.

## 1. Conferir ferramentas

```sh
docker version
docker compose version
python3 --version
```


## 2. Instalação independente

Na raiz do projeto baixado:

```sh
cp .env.local.example .env.local
openssl rand -hex 32
```

Preencher `APP_SECRET` com a chave gerada e `DB_PASSWORD` com uma senha.

```sh
docker compose --env-file .env.local -f compose.avaliacao.yaml config --quiet

docker compose --env-file .env.local -f compose.avaliacao.yaml up -d --build

docker compose --env-file .env.local -f compose.avaliacao.yaml ps
```

Acesso: http://localhost:8080. Banco `catalogo_livros`, usuário `catalogo_app`, hostname `banco` e porta interna `3306`.


## 3. Dependências

```sh
docker compose --env-file .env.local -f compose.avaliacao.yaml exec app composer install
```


## 4. Conferir Symfony

```sh
docker compose --env-file .env.local -f compose.avaliacao.yaml exec --user www-data app php bin/console about

docker compose --env-file .env.local -f compose.avaliacao.yaml exec --user www-data app php bin/console debug:router
```


## 5. Conferir extensões

```sh
docker compose --env-file .env.local -f compose.avaliacao.yaml exec app php -m

docker compose --env-file .env.local -f compose.avaliacao.yaml exec app php -r 'print_r(PDO::getAvailableDrivers());'
```


## 6. Conferir banco

```sh
docker compose --env-file .env.local -f compose.avaliacao.yaml exec banco mariadb -u catalogo_app -p catalogo_livros
```

No cliente MariaDB:

```sql
SELECT DATABASE(), VERSION();
SHOW FULL TABLES;
DESCRIBE Autor;
DESCRIBE Assunto;
DESCRIBE Livro;
SHOW CREATE TABLE Livro_Autor;
SHOW CREATE TABLE Livro_Assunto;
SELECT * FROM vw_livros_por_autor ORDER BY Autor, CodAu, Titulo, Codl;
EXIT;
```


## 7. Sequência dos SQLs

Arquivos executados automaticamente na primeira inicialização:

```text
sql/Autor.sql
sql/Assunto.sql
sql/Livro.sql
sql/Livro_Autor.sql
sql/Livro_Assunto.sql
sql/Livro_status.sql
sql/vw_livros_por_autor.sql
```


## 8. Validar PHP

```sh
docker compose --env-file .env.local -f compose.avaliacao.yaml exec app php -l src/Controller/CatalogoController.php

docker compose --env-file .env.local -f compose.avaliacao.yaml exec app php -l src/Service/Catalogo.php

docker compose --env-file .env.local -f compose.avaliacao.yaml exec app php -l src/Service/CatalogoValidator.php

docker compose --env-file .env.local -f compose.avaliacao.yaml exec app php -l src/Service/ConexaoBanco.php
```


## 9. Validar templates e configuração

```sh
docker compose --env-file .env.local -f compose.avaliacao.yaml exec --user www-data app php bin/console lint:twig templates

docker compose --env-file .env.local -f compose.avaliacao.yaml exec --user www-data app php bin/console lint:yaml config

docker compose --env-file .env.local -f compose.avaliacao.yaml exec --user www-data app php bin/console lint:container
```


## 10. Executar testes

```sh
docker compose --env-file .env.local -f compose.avaliacao.yaml exec app vendor/bin/phpunit

python3 scripts/smoke.py --url http://localhost:8080
```

O teste HTTP cria registros temporários e os remove ao terminar.


## 11. Reiniciar e diagnosticar

```sh
docker compose --env-file .env.local -f compose.avaliacao.yaml logs --tail=100 app

docker compose --env-file .env.local -f compose.avaliacao.yaml restart app

docker compose --env-file .env.local -f compose.avaliacao.yaml down

docker compose --env-file .env.local -f compose.avaliacao.yaml up -d
```
