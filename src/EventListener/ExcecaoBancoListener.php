<?php

namespace App\EventListener;

use Doctrine\DBAL\Exception\ConnectionException;
use Doctrine\DBAL\Exception\DeadlockException;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

#[AsEventListener(event: 'kernel.exception')]
class ExcecaoBancoListener
{
    public function __invoke(ExceptionEvent $evento): void
    {
        $excecao = $evento->getThrowable();
        if ($excecao instanceof ConnectionException) {
            $evento->setResponse(new Response('O banco de dados está temporariamente indisponível. Tente novamente em instantes.', 503, ['Content-Type' => 'text/plain; charset=UTF-8', 'Retry-After' => '30']));
        } elseif ($excecao instanceof DeadlockException || $excecao instanceof LockWaitTimeoutException) {
            $evento->setResponse(new Response('Outro cadastro está sendo atualizado. Aguarde alguns instantes e envie novamente.', 409, ['Content-Type' => 'text/plain; charset=UTF-8']));
        }
    }
}
