-- Assuntos utilizados na classificação dos livros.
CREATE TABLE IF NOT EXISTS Assunto (
    codAs INT AUTO_INCREMENT PRIMARY KEY,
    Descricao VARCHAR(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
