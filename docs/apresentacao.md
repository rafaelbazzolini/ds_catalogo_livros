# Visão técnica

- Symfony recebe as requisições e Twig renderiza as páginas.
- O serviço de catálogo concentra consultas e transações; o validador trata as entradas.
- Livros têm vários autores e assuntos, ligados por tabelas com chaves compostas.
- A gravação do livro e dos vínculos ocorre na mesma transação.
- Autores e assuntos vinculados são protegidos por chave estrangeira.
- O relatório consulta a view e agrupa por código do autor. Dompdf gera o PDF.

## Conferência funcional

1. Cadastrar autores, assuntos e um livro com vários vínculos.
2. Editar o livro e usar o cadastro rápido de autor ou assunto.
3. Tentar excluir um cadastro vinculado.
4. Conferir pesquisa, paginação e relatório em HTML e PDF.
5. Executar os testes conforme o [guia de instalação](implantacao.md).

Os testes cobrem validação, transações, relacionamentos e paginação. Foram escritos para verificar a implementação, sem adoção de TDD desde o início.
