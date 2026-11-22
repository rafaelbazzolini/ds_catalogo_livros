-- Dados do livro; valor armazenado em decimal.
CREATE TABLE IF NOT EXISTS Livro (
    Codl INT AUTO_INCREMENT PRIMARY KEY,
    Titulo VARCHAR(40) NOT NULL,
    Editora VARCHAR(40) NOT NULL,
    Edicao INT NOT NULL,
    AnoPublicacao VARCHAR(4) NOT NULL,
    Valor DECIMAL(10,2) NOT NULL,
    CONSTRAINT ck_livro_valor CHECK (Valor >= 0),
    CONSTRAINT ck_livro_edicao CHECK (Edicao > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
