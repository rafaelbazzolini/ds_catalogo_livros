<?php

namespace App\Service;

class CatalogoValidator
{
    public function validate(string $tipo, array &$dados): array
    {
        $erros = [];
        $metadados = Catalogo::TYPES[$tipo];
        $campos = $tipo === 'livros' ? ['Titulo' => 40, 'Editora' => 40] : [$metadados['field'] => $metadados['max']];
        foreach ($campos as $campo => $limite) {
            $dados[$campo] = trim((string) ($dados[$campo] ?? ''));
            if ($dados[$campo] === '' || mb_strlen($dados[$campo]) > $limite) {
                $erros[] = "$campo deve conter entre 1 e $limite caracteres.";
            }
        }
        if ($tipo !== 'livros') {
            return $erros;
        }
        if (!in_array($dados['Status'] ?? null, ['disponivel', 'indisponivel'], true)) {
            $erros[] = 'Selecione um status válido para o livro.';
        }
        if (!preg_match('/^[1-9][0-9]{0,8}$/D', (string) ($dados['Edicao'] ?? ''))) {
            $erros[] = 'Informe uma edição inteira maior que zero, com até 9 dígitos.';
        }
        if (!preg_match('/^[1-9][0-9]{3}$/D', (string) ($dados['AnoPublicacao'] ?? ''))) {
            $erros[] = 'Informe o ano de publicação com quatro dígitos.';
        }
        $valorMonetario = trim((string) ($dados['Valor'] ?? ''));
        if (!preg_match('/^(?:[0-9]{1,8}|[0-9]{1,2}(?:\.[0-9]{3}){1,2}),[0-9]{2}$/D', $valorMonetario)) {
            $erros[] = 'Informe o valor no formato 1.234,56, até R$ 99.999.999,99.';
        } else {
            $dados['Valor'] = str_replace(',', '.', str_replace('.', '', $valorMonetario));
        }
        foreach (['autores', 'assuntos'] as $chave) {
            $dados[$chave] = (array) ($dados[$chave] ?? []);
            if (!$dados[$chave] || array_filter($dados[$chave], static fn ($id) => !is_scalar($id) || !ctype_digit((string) $id) || (int) $id < 1)) {
                $erros[] = "Selecione pelo menos um item válido em $chave.";
            }
        }
        return $erros;
    }
}
