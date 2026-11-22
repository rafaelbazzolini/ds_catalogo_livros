<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

class Catalogo
{
    public const TYPES = [
        'livros' => ['table' => 'Livro', 'id' => 'Codl', 'label' => 'Livros', 'field' => 'Titulo', 'max' => 40],
        'autores' => ['table' => 'Autor', 'id' => 'CodAu', 'label' => 'Autores', 'field' => 'Nome', 'max' => 40],
        'assuntos' => ['table' => 'Assunto', 'id' => 'codAs', 'label' => 'Assuntos', 'field' => 'Descricao', 'max' => 20],
    ];

    public function __construct(private Connection $conexao) {}

    public function list(string $tipo, string $busca = '', int $pagina = 1, int $porPagina = 10): array
    {
        $porPagina = in_array($porPagina, [0, 5, 10, 25, 50], true) ? $porPagina : 10;
        $metadados = self::TYPES[$tipo];
        $condicao = $busca === '' ? '' : " WHERE {$metadados['field']} LIKE :busca";
        $parametros = $busca === '' ? [] : ['busca' => '%'.$busca.'%'];
        $total = (int) $this->conexao->fetchOne("SELECT COUNT(*) FROM {$metadados['table']}".$condicao, $parametros);
        $paginas = $porPagina === 0 ? 1 : max(1, (int) ceil($total / $porPagina));
        $pagina = max(1, min($paginas, $pagina));
        $deslocamento = ($pagina - 1) * $porPagina;
        if ($porPagina === 0) {
            $registros = $this->conexao->fetchAllAssociative("SELECT * FROM {$metadados['table']}".$condicao." ORDER BY {$metadados['field']}, {$metadados['id']}", $parametros);
        } else {
            $registros = $this->conexao->fetchAllAssociative("SELECT * FROM {$metadados['table']}".$condicao." ORDER BY {$metadados['field']}, {$metadados['id']} LIMIT $porPagina OFFSET $deslocamento", $parametros);
        }
        return ['registros' => $registros, 'total' => $total, 'paginas' => $paginas, 'pagina' => $pagina, 'porPagina' => $porPagina, 'deslocamento' => $deslocamento];
    }

    public function choices(string $tipo): array
    {
        $metadados = self::TYPES[$tipo];
        return $this->conexao->fetchAllAssociative("SELECT * FROM {$metadados['table']} ORDER BY {$metadados['field']}");
    }

    public function selectedChoices(string $tipo, array $identificadores): array
    {
        $identificadores = array_values(array_filter($identificadores, static fn ($id) => is_scalar($id) && ctype_digit((string) $id) && (int) $id > 0));
        if (!$identificadores) return [];
        $metadados = self::TYPES[$tipo];
        return $this->conexao->fetchAllAssociative("SELECT * FROM {$metadados['table']} WHERE {$metadados['id']} IN (?) ORDER BY {$metadados['field']}", [$identificadores], [\Doctrine\DBAL\ArrayParameterType::INTEGER]);
    }

    public function find(string $tipo, int $id): array|false
    {
        $metadados = self::TYPES[$tipo];
        $registro = $this->conexao->fetchAssociative("SELECT * FROM {$metadados['table']} WHERE {$metadados['id']} = ?", [$id]);
        if ($registro && $tipo === 'livros') {
            $registro['autores'] = $this->conexao->fetchFirstColumn('SELECT Autor_CodAu FROM Livro_Autor WHERE Livro_Codl = ?', [$id]);
            $registro['assuntos'] = $this->conexao->fetchFirstColumn('SELECT Assunto_codAs FROM Livro_Assunto WHERE Livro_Codl = ?', [$id]);
        }
        return $registro;
    }

    public function save(string $tipo, ?int $id, array $dados): int
    {
        $metadados = self::TYPES[$tipo];
        return $this->conexao->transactional(function () use ($tipo, $id, $dados, $metadados): int {
            $valores = array_intersect_key($dados, array_flip($tipo === 'livros' ? ['Titulo', 'Editora', 'Edicao', 'AnoPublicacao', 'Valor', 'Status'] : [$metadados['field']]));
            if ($id) {
                $this->conexao->update($metadados['table'], $valores, [$metadados['id'] => $id]);
            } else {
                $this->conexao->insert($metadados['table'], $valores);
                $id = (int) $this->conexao->lastInsertId();
            }
            if ($tipo !== 'livros') {
                return $id;
            }
            foreach (['autores' => ['Livro_Autor', 'Autor_CodAu'], 'assuntos' => ['Livro_Assunto', 'Assunto_codAs']] as $chave => [$tabela, $coluna]) {
                $this->conexao->delete($tabela, ['Livro_Codl' => $id]);
                foreach (array_unique($dados[$chave]) as $idRelacionado) {
                    $this->conexao->insert($tabela, ['Livro_Codl' => $id, $coluna => $idRelacionado]);
                }
            }
            return $id;
        });
    }

    public function delete(string $tipo, int $id): void
    {
        $metadados = self::TYPES[$tipo];
        $this->conexao->delete($metadados['table'], [$metadados['id'] => $id]);
    }

    public function dashboard(): array
    {
        $totais = $this->conexao->fetchAssociative("SELECT COUNT(*) AS livros,
            COALESCE(SUM(Status = 'disponivel'), 0) AS disponiveis,
            COALESCE(SUM(Status = 'indisponivel'), 0) AS indisponiveis,
            COALESCE(SUM(Valor), 0) AS value,
            (SELECT COUNT(*) FROM Autor) AS autores,
            (SELECT COUNT(*) FROM Assunto) AS assuntos
            FROM Livro");
        $recentes = $this->conexao->fetchAllAssociative('SELECT Codl, Titulo, Editora, Status, Valor FROM Livro ORDER BY Codl DESC LIMIT 5');
        return ['totais' => $totais, 'recentes' => $recentes];
    }

    public function report(): array
    {
        $grupos = [];
        foreach ($this->conexao->fetchAllAssociative('SELECT * FROM vw_livros_por_autor ORDER BY Autor, CodAu, Titulo, Codl') as $registro) {
            $grupos[$registro['CodAu']]['name'] = $registro['Autor'];
            $grupos[$registro['CodAu']]['livros'][] = $registro;
        }
        return $grupos;
    }
}
