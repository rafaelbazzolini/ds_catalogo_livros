-- Uma linha por livro/autor; assuntos agregados evitam duplicações.
CREATE OR REPLACE VIEW vw_livros_por_autor AS
SELECT a.CodAu, a.Nome AS Autor, l.Codl, l.Titulo, l.Editora, l.Edicao,
       l.AnoPublicacao, l.Valor, l.Status,
       (SELECT GROUP_CONCAT(s.Descricao ORDER BY s.Descricao SEPARATOR ', ')
        FROM Livro_Assunto ls JOIN Assunto s ON s.codAs = ls.Assunto_codAs
        WHERE ls.Livro_Codl = l.Codl) AS Assuntos
FROM Autor a
JOIN Livro_Autor la ON la.Autor_CodAu = a.CodAu
JOIN Livro l ON l.Codl = la.Livro_Codl;
