-- Chave composta impede repetir o vínculo livro/autor.
CREATE TABLE IF NOT EXISTS Livro_Autor (
    Livro_Codl INT NOT NULL,
    Autor_CodAu INT NOT NULL,
    PRIMARY KEY (Livro_Codl, Autor_CodAu),
    KEY idx_autor (Autor_CodAu),
    FOREIGN KEY (Livro_Codl) REFERENCES Livro(Codl) ON DELETE CASCADE,
    FOREIGN KEY (Autor_CodAu) REFERENCES Autor(CodAu) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
