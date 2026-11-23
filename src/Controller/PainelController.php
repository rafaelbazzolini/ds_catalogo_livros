<?php

namespace App\Controller;

use App\Service\Catalogo;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PainelController extends AbstractController
{
    #[Route('/', name: 'home', methods: ['GET'])]
    public function index(Catalogo $catalogo): Response
    {
        // Os totais não dependem dos vínculos entre livros e autores.
        return $this->render('painel/index.html.twig', $catalogo->dashboard());
    }
}
