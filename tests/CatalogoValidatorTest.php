<?php

namespace App\Tests;

use App\Service\CatalogoValidator;
use PHPUnit\Framework\TestCase;

class CatalogoValidatorTest extends TestCase
{
    public function testNormalizesBrazilianMoneyWithoutFloatConversion(): void
    {
        $dados = $this->book();
        self::assertSame([], (new CatalogoValidator())->validate('livros', $dados));
        self::assertSame('1234.56', $dados['Valor']);
    }

    public function testRejectsMissingRelationshipsAndInvalidBookFields(): void
    {
        $dados = array_replace($this->book(), ['Titulo' => str_repeat('a', 41), 'Edicao' => '0', 'AnoPublicacao' => '26', 'Valor' => '-1,00', 'autores' => [], 'assuntos' => ['abc']]);
        self::assertCount(6, (new CatalogoValidator())->validate('livros', $dados));
    }

    public function testSubjectLengthCountsUnicodeCharacters(): void
    {
        $dados = ['Descricao' => str_repeat('ç', 20)];
        self::assertSame([], (new CatalogoValidator())->validate('assuntos', $dados));
        $dados['Descricao'] .= 'x';
        self::assertCount(1, (new CatalogoValidator())->validate('assuntos', $dados));
    }

    public function testRejectsValuesExceedingDatabasePrecision(): void
    {
        $dados = array_replace($this->book(), ['Valor' => '100.000.000,00']);
        self::assertCount(1, (new CatalogoValidator())->validate('livros', $dados));
    }

    public function testRejectsMissingOrUnknownStatus(): void
    {
        foreach ([null, '', 'emprestado'] as $status) {
            $dados = array_replace($this->book(), ['Status' => $status]);
            self::assertSame(['Selecione um status válido para o livro.'], (new CatalogoValidator())->validate('livros', $dados));
        }
    }

    private function book(): array
    {
        return ['Status' => 'disponivel', 'Titulo' => 'Dom Casmurro', 'Editora' => 'Companhia das Letras', 'Edicao' => '1', 'AnoPublicacao' => '1899', 'Valor' => '1.234,56', 'autores' => ['1', '2'], 'assuntos' => ['1']];
    }
}
