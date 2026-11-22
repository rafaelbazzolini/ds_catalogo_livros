<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

// Os testes de integração usam a mesma conexão do ambiente local.
(new Dotenv())->usePutenv()->bootEnv(dirname(__DIR__).'/.env');
