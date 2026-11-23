<?php

namespace App\Controller;

use App\Service\Catalogo;
use App\Service\CatalogoValidator;
use Doctrine\DBAL\Exception\ConnectionException;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/cadastro/assuntos', name: 'assuntos_')]
class AssuntoController extends AbstractController
{
    #[Route('', name: 'lista', methods: ['GET'])]
    public function listar(Request $requisicao, Catalogo $catalogo): Response
    {
        $tipo = 'assuntos';
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

        return $this->render('assunto/lista.html.twig', [
            ...$resultado,
            'tipo' => $tipo,
            'metadados' => Catalogo::TYPES[$tipo],
            'busca' => $busca,
        ]);
    }

    #[Route('/novo', name: 'novo', methods: ['GET', 'POST'])]
    public function novo(Request $requisicao, Catalogo $catalogo, CatalogoValidator $validador): Response
    {
        // Abre o formulário e grava o novo cadastro.
        return $this->formulario($requisicao, $catalogo, $validador);
    }

    #[Route('/{id}/editar', name: 'editar', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function editar(int $id, Request $requisicao, Catalogo $catalogo, CatalogoValidator $validador): Response
    {
        // Carrega e atualiza o cadastro informado.
        return $this->formulario($requisicao, $catalogo, $validador, $id);
    }

    #[Route('/{id}/excluir', name: 'excluir', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function excluir(int $id, Request $requisicao, Catalogo $catalogo): Response
    {
        $tipo = 'assuntos';
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

        return $this->redirectToRoute('assuntos_lista', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/opcoes', name: 'opcoes', methods: ['GET'])]
    public function opcoes(Request $requisicao, Catalogo $catalogo): Response
    {
        $tipo = 'assuntos';
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

    #[Route('/rapido', name: 'rapido', methods: ['POST'])]
    public function cadastroRapido(
        Request $requisicao,
        Catalogo $catalogo,
        CatalogoValidator $validador
    ): Response {
        $tipo = 'assuntos';
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

    private function formulario(
        Request $requisicao,
        Catalogo $catalogo,
        CatalogoValidator $validador,
        ?int $id = null
    ): Response {
        $tipo = 'assuntos';
        // Sem código, abre um novo cadastro; com código, carrega a edição.
        $dados = $id ? $catalogo->find($tipo, $id) : [];

        if ($dados === false) {
            throw $this->createNotFoundException('Cadastro não encontrado.');
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
                        'assuntos_editar',
                        ['id' => $idSalvo],
                        Response::HTTP_SEE_OTHER
                    );
                } catch (ForeignKeyConstraintViolationException) {
                    $erros[] = 'Um autor ou assunto selecionado não está mais disponível. Atualize a seleção.';
                }
            }
        }

        return $this->render('assunto/formulario.html.twig', [
            'tipo' => $tipo,
            'metadados' => Catalogo::TYPES[$tipo],
            'dados' => $dados,
            'id' => $id,
            'erros' => $erros,
        ], new Response(status: $erros ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }
}
