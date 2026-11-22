<?php

namespace App\Tests;

use App\Service\Catalogo;
use App\Service\ConexaoBanco;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use PHPUnit\Framework\TestCase;

class IntegracaoCatalogoTest extends TestCase
{
    private Connection $conexao;
    private Catalogo $catalogo;

    protected function setUp(): void
    {
        if (!getenv('DB_DATABASE')) {
            self::markTestSkipped('DB_DATABASE não configurada para os testes de integração.');
        }
        $this->conexao = ConexaoBanco::create(
            getenv('DB_HOST') ?: 'mariadb',
            (int) (getenv('DB_PORT') ?: 3306),
            getenv('DB_DATABASE'),
            getenv('DB_USERNAME') ?: '',
            getenv('DB_PASSWORD') ?: '',
            getenv('DB_CHARSET') ?: 'utf8mb4'
        );
        $this->conexao->beginTransaction();
        $this->catalogo = new Catalogo($this->conexao);
    }

    protected function tearDown(): void
    {
        if (isset($this->conexao)) {
            $this->conexao->rollBack();
            $this->conexao->close();
        }
    }

    public function testViewReturnsOneBookPerAuthorWithoutMultiplyingSubjects(): void
    {
        $autores = [];
        $assuntos = [];
        foreach (['A', 'B'] as $sufixo) {
            $this->conexao->insert('Autor', ['Nome' => 'Integração '.$sufixo]);
            $autores[] = (int) $this->conexao->lastInsertId();
            $this->conexao->insert('Assunto', ['Descricao' => 'Tema '.$sufixo]);
            $assuntos[] = (int) $this->conexao->lastInsertId();
        }
        $this->catalogo->save('livros', null, $this->book($autores, $assuntos));
        $id = (int) $this->conexao->fetchOne('SELECT MAX(Codl) FROM Livro');
        $registros = $this->conexao->fetchAllAssociative('SELECT * FROM vw_livros_por_autor WHERE Codl = ?', [$id]);
        self::assertCount(2, $registros);
        foreach ($registros as $registro) {
            self::assertSame('Tema A, Tema B', $registro['Assuntos']);
            self::assertSame('49.90', $registro['Valor']);
        }
        $this->catalogo->delete('livros', $id);
        self::assertSame(0, (int) $this->conexao->fetchOne('SELECT COUNT(*) FROM Livro_Autor WHERE Livro_Codl = ?', [$id]));
        self::assertSame(0, (int) $this->conexao->fetchOne('SELECT COUNT(*) FROM Livro_Assunto WHERE Livro_Codl = ?', [$id]));
        self::assertNotFalse($this->catalogo->find('autores', $autores[0]));
    }

    public function testInvalidRelationshipRollsBackBookCreation(): void
    {
        $antes = (int) $this->conexao->fetchOne('SELECT COUNT(*) FROM Livro');
        try {
            $this->catalogo->save('livros', null, $this->book([2147483647], [2147483647]));
            self::fail('O banco deveria rejeitar o vínculo inexistente.');
        } catch (ForeignKeyConstraintViolationException) {
            self::assertSame($antes, (int) $this->conexao->fetchOne('SELECT COUNT(*) FROM Livro'));
        }
    }

    public function testPaginationRespectsPageSizeSearchAndPageBounds(): void
    {
        $prefixo = 'Paginação '.bin2hex(random_bytes(4));
        for ($indice = 1; $indice <= 7; ++$indice) {
            $this->conexao->insert('Autor', ['Nome' => $prefixo.' '.$indice]);
        }
        $resultado = $this->catalogo->list('autores', $prefixo, 1, 5);
        self::assertCount(5, $resultado['registros']);
        self::assertSame(7, $resultado['total']);
        self::assertSame(2, $resultado['paginas']);
        $ultima = $this->catalogo->list('autores', $prefixo, 99, 5);
        self::assertSame(2, $ultima['pagina']);
        self::assertCount(2, $ultima['registros']);
        self::assertSame(5, $ultima['deslocamento']);
        $padrao = $this->catalogo->list('autores', $prefixo, -1, -5);
        self::assertSame(10, $padrao['porPagina']);
        self::assertSame(1, $padrao['pagina']);
        $todos = $this->catalogo->list('autores', $prefixo, 99, 0);
        self::assertCount(7, $todos['registros']);
        self::assertSame(0, $todos['porPagina']);
        self::assertSame(1, $todos['paginas']);
        self::assertSame(1, $todos['pagina']);
        self::assertSame(0, $todos['deslocamento']);
        $vazio = $this->catalogo->list('autores', $prefixo.' inexistente', 1, 0);
        self::assertSame([], $vazio['registros']);
        self::assertSame(1, $vazio['paginas']);
    }

    public function testDashboardCountsBooksOnceAndReflectsAvailability(): void
    {
        $antes = $this->catalogo->dashboard()['totais'];
        $this->conexao->insert('Livro', ['Titulo' => 'Dashboard teste', 'Editora' => 'Teste', 'Edicao' => 1, 'AnoPublicacao' => '2026', 'Valor' => '123.45', 'Status' => 'indisponivel']);
        $id = (int) $this->conexao->lastInsertId();
        $dashboard = $this->catalogo->dashboard();
        self::assertSame((int) $antes['livros'] + 1, (int) $dashboard['totais']['livros']);
        self::assertSame((int) $antes['disponiveis'], (int) $dashboard['totais']['disponiveis']);
        self::assertSame((int) $antes['indisponiveis'] + 1, (int) $dashboard['totais']['indisponiveis']);
        self::assertEqualsWithDelta((float) $antes['value'] + 123.45, (float) $dashboard['totais']['value'], 0.001);
        self::assertSame($id, $dashboard['recentes'][0]['Codl']);
        self::assertLessThanOrEqual(5, count($dashboard['recentes']));
    }

    public function testRelationshipSearchPaginatesAndOnlyLoadsSelectedItems(): void
    {
        $prefixo = 'Busca '.bin2hex(random_bytes(4));
        $identificadores = [];
        for ($indice = 1; $indice <= 31; ++$indice) {
            $this->conexao->insert('Autor', ['Nome' => $prefixo.' '.str_pad((string) $indice, 2, '0', STR_PAD_LEFT)]);
            $identificadores[] = (int) $this->conexao->lastInsertId();
        }
        $primeira = $this->catalogo->list('autores', $prefixo, 1, 25);
        $segunda = $this->catalogo->list('autores', $prefixo, 2, 25);
        self::assertCount(25, $primeira['registros']);
        self::assertCount(6, $segunda['registros']);
        self::assertSame(31, $primeira['total']);
        $selecionados = $this->catalogo->selectedChoices('autores', [$identificadores[0], $identificadores[30], 'invalid']);
        self::assertSame([$identificadores[0], $identificadores[30]], array_column($selecionados, 'CodAu'));
        self::assertSame([], $this->catalogo->selectedChoices('autores', []));
    }

    private function book(array $autores, array $assuntos): array
    {
        return ['Status' => 'disponivel', 'Titulo' => 'Livro de integração', 'Editora' => 'Editora', 'Edicao' => 1, 'AnoPublicacao' => '2026', 'Valor' => '49.90', 'autores' => $autores, 'assuntos' => $assuntos];
    }
}
