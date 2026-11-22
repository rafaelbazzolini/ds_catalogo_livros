-- Exclusão do livro remove os vínculos, preservando os assuntos.
CREATE TABLE IF NOT EXISTS Livro_Assunto (
    Livro_Codl INT NOT NULL,
    Assunto_codAs INT NOT NULL,
    PRIMARY KEY (Livro_Codl, Assunto_codAs),
    KEY idx_assunto (Assunto_codAs),
    FOREIGN KEY (Livro_Codl) REFERENCES Livro(Codl) ON DELETE CASCADE,
    FOREIGN KEY (Assunto_codAs) REFERENCES Assunto(codAs) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
