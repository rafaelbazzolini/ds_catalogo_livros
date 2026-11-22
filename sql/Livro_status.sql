-- Compatibilidade com instalações anteriores ao campo Status.
ALTER TABLE Livro
    ADD COLUMN IF NOT EXISTS Status VARCHAR(12) NOT NULL DEFAULT 'disponivel'
    CHECK (Status IN ('disponivel', 'indisponivel'));
