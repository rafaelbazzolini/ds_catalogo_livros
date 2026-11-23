<?php

namespace App\Controller;

use App\Service\Catalogo;
use App\Service\CatalogoValidator;
use Doctrine\DBAL\Exception\ConnectionException;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Dompdf\Dompdf;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;

class CatalogoController extends AbstractController
{
    #[Route('/', name: 'home', methods: ['GET'])]
    public function home(Catalogo $catalogo): Response
    {
        // Os totais não dependem dos vínculos entre livros e autores.
        return $this->render('painel/index.html.twig', $catalogo->dashboard());
    }

    #[Route(
        '/cadastro/{tipo}',
        name: 'catalog_list',
        requirements: ['tipo' => 'livros|autores|assuntos'],
        methods: ['GET']
    )]
    public function list(string $tipo, Request $requisicao, Catalogo $catalogo): Response
    {
        // A pesquisa e o tamanho da página acompanham a navegação.
        $busca = trim($requisicao->query->getString('q'));

        try {
            $resultado = $catalogo->list(
                $tipo,
                $busca,
                $requisicao->query->getInt('pagina', 1),
                $requisicao->query->getInt('porPagina', 10)
            );
        } catch (ConnectionException) {
            return $this->render('erros/indisponivel.html.twig', status: Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return $this->render(match ($tipo) {
            'livros' => 'livro/lista.html.twig',
            'autores' => 'autor/lista.html.twig',
            'assuntos' => 'assunto/lista.html.twig',
        }, [
            ...$resultado,
            'tipo' => $tipo,
            'metadados' => Catalogo::TYPES[$tipo],
            'busca' => $busca,
        ]);
    }

    #[Route(
        '/cadastro/{tipo}/novo',
        name: 'catalog_new',
        requirements: ['tipo' => 'livros|autores|assuntos'],
        methods: ['GET', 'POST']
    )]
    #[Route(
        '/cadastro/{tipo}/{id}/editar',
        name: 'catalog_edit',
        requirements: ['tipo' => 'livros|autores|assuntos', 'id' => '\d+'],
        methods: ['GET', 'POST']
    )]
    public function edit(
        string $tipo,
        Request $requisicao,
        Catalogo $catalogo,
        CatalogoValidator $validador,
        ?int $id = null
    ): Response {
        // Sem código, abre um novo cadastro; com código, carrega a edição.
        $dados = $id ? $catalogo->find($tipo, $id) : [];

        if ($dados === false) {
            throw $this->createNotFoundException('Cadastro não encontrado.');
        }

        if ($id && $tipo === 'livros') {
            $dados['Valor'] = number_format((float) $dados['Valor'], 2, ',', '.');
        }

        $erros = [];

        if ($requisicao->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('save_'.$tipo, $requisicao->request->getString('_token'))) {
                throw new HttpException(403, 'Sessão expirada. Atualize a página e tente novamente.');
            }

            $dados = $requisicao->request->all();
            $normalizados = $dados;
            $erros = $validador->validate($tipo, $normalizados);

            if (!$erros) {
                try {
                    $idSalvo = $catalogo->save($tipo, $id, $normalizados);
                    $this->addFlash('success', 'Cadastro salvo com sucesso.');

                    // O redirecionamento evita repetir a gravação ao atualizar a página.
                    return $this->redirectToRoute(
                        'catalog_edit',
                        ['tipo' => $tipo, 'id' => $idSalvo],
                        Response::HTTP_SEE_OTHER
                    );
                } catch (ForeignKeyConstraintViolationException) {
                    $erros[] = 'Um autor ou assunto selecionado não está mais disponível. Atualize a seleção.';
                }
            }
        }

        return $this->render(match ($tipo) {
            'livros' => 'livro/formulario.html.twig',
            'autores' => 'autor/formulario.html.twig',
            'assuntos' => 'assunto/formulario.html.twig',
        }, [
            'tipo' => $tipo,
            'metadados' => Catalogo::TYPES[$tipo],
            'dados' => $dados,
            'id' => $id,
            'erros' => $erros,
            'autores' => $tipo === 'livros'
                ? $catalogo->selectedChoices('autores', (array) ($dados['autores'] ?? []))
                : [],
            'assuntos' => $tipo === 'livros'
                ? $catalogo->selectedChoices('assuntos', (array) ($dados['assuntos'] ?? []))
                : [],
        ], new Response(status: $erros ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route(
        '/cadastro/{tipo}/opcoes',
        name: 'catalog_options',
        requirements: ['tipo' => 'autores|assuntos'],
        methods: ['GET']
    )]
    public function options(string $tipo, Request $requisicao, Catalogo $catalogo): Response
    {
        // Limita a busca para não carregar todos os autores e assuntos no formulário.
        $resultado = $catalogo->list(
            $tipo,
            mb_substr(trim($requisicao->query->getString('q')), 0, 40),
            $requisicao->query->getInt('pagina', 1),
            25
        );
        $metadados = Catalogo::TYPES[$tipo];
        $itens = array_map(
            static fn (array $item) => [
                'id' => $item[$metadados['id']],
                'label' => $item[$metadados['field']],
            ],
            $resultado['registros']
        );

        return $this->json([
            'items' => $itens,
            'hasMore' => $resultado['pagina'] < $resultado['paginas'],
        ]);
    }

    #[Route(
        '/cadastro/{tipo}/rapido',
        name: 'catalog_quick_create',
        requirements: ['tipo' => 'autores|assuntos'],
        methods: ['POST']
    )]
    public function quickCreate(
        string $tipo,
        Request $requisicao,
        Catalogo $catalogo,
        CatalogoValidator $validador
    ): Response {
        // Usa a mesma validação do cadastro completo, com retorno para o modal.
        if (!$this->isCsrfTokenValid('save_'.$tipo, $requisicao->request->getString('_token'))) {
            return $this->json([
                'erros' => ['Sessão expirada. Atualize a página antes de tentar novamente.'],
            ], Response::HTTP_FORBIDDEN);
        }

        $dados = $requisicao->request->all();
        $erros = $validador->validate($tipo, $dados);

        if ($erros) {
            return $this->json(['erros' => $erros], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $id = $catalogo->save($tipo, null, $dados);
        $metadados = Catalogo::TYPES[$tipo];
        $itens = [['id' => $id, 'label' => $dados[$metadados['field']]]];

        return $this->json(['id' => $id, 'items' => $itens], Response::HTTP_CREATED);
    }

    #[Route(
        '/cadastro/{tipo}/{id}/excluir',
        name: 'catalog_delete',
        requirements: ['tipo' => 'livros|autores|assuntos', 'id' => '\d+'],
        methods: ['POST']
    )]
    public function delete(string $tipo, int $id, Request $requisicao, Catalogo $catalogo): Response
    {
        // A chave estrangeira protege autores e assuntos usados por livros.
        if (!$this->isCsrfTokenValid('delete_'.$tipo.'_'.$id, $requisicao->request->getString('_token'))) {
            throw new HttpException(403, 'Sessão expirada.');
        }

        if (!$catalogo->find($tipo, $id)) {
            throw $this->createNotFoundException('Cadastro não encontrado.');
        }

        try {
            $catalogo->delete($tipo, $id);
            $this->addFlash('success', 'Cadastro excluído com sucesso.');
        } catch (ForeignKeyConstraintViolationException) {
            $this->addFlash('danger', 'Este cadastro está vinculado a um livro. Remova o vínculo antes de excluir.');
        }

        return $this->redirectToRoute('catalog_list', ['tipo' => $tipo], Response::HTTP_SEE_OTHER);
    }

    #[Route('/relatorio', name: 'report', methods: ['GET'])]
    public function report(Request $requisicao, Catalogo $catalogo): Response
    {
        // Tela e PDF usam os mesmos grupos consultados na view.
        $dados = [
            'grupos' => $catalogo->report(),
            'geradoEm' => new \DateTimeImmutable('now', new \DateTimeZone('America/Sao_Paulo')),
        ];

        if ($requisicao->query->getString('format') !== 'pdf') {
            return $this->render('relatorio/index.html.twig', $dados);
        }

        $pdf = new Dompdf(['isRemoteEnabled' => false]);
        $pdf->loadHtml($this->renderView('relatorio/pdf.html.twig', $dados));
        $pdf->setPaper('A4', 'landscape');
        $pdf->render();

        return new Response($pdf->output(), Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="livros-por-autor.pdf"',
        ]);
    }
}
