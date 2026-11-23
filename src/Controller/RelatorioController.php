<?php

namespace App\Controller;

use App\Service\Catalogo;
use Dompdf\Dompdf;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class RelatorioController extends AbstractController
{
    #[Route('/relatorio', name: 'report', methods: ['GET'])]
    public function gerar(Request $requisicao, Catalogo $catalogo): Response
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
