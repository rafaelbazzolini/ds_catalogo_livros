# Instalação

Requer Docker com Docker Compose. A aplicação e o MariaDB são executados em containers separados.

## Iniciar

Baixe ou clone o repositório e, na raiz do projeto, copie o arquivo de exemplo:

```sh
cp .env.local.example .env.local
```

Preencha `APP_SECRET` com uma chave aleatória e `DB_PASSWORD` com uma senha. Os demais valores podem ser mantidos. Para gerar a chave, pode ser usado `openssl rand -hex 32`.

```sh
docker compose --env-file .env.local -f compose.avaliacao.yaml up -d --build
```

Acesse http://localhost:8080. As dependências, tabelas e view são preparadas automaticamente. O banco inicia sem registros.

Se a porta estiver ocupada, acrescente `APP_PORT=8081` ao `.env.local` e execute o comando novamente.

## Testes

```sh
docker compose --env-file .env.local -f compose.avaliacao.yaml exec app vendor/bin/phpunit
```

## Encerrar

```sh
docker compose --env-file .env.local -f compose.avaliacao.yaml down
```

Os dados são preservados em volume. A criação das tabelas ocorre somente na primeira inicialização; alterar a senha no arquivo não altera um usuário já criado.

Os SQLs estão em [sql](../sql), com a sequência em [ordem.txt](../sql/ordem.txt). Não é necessário executá-los manualmente para esta instalação.

O ambiente é destinado à avaliação local, com debug habilitado e sem autenticação.
