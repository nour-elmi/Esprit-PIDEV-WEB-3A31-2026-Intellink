<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

// CHANGEMENT: la methode bootEnv existe deja, suppression du guard redondant.
// Ancien code (garde):
// if (method_exists(Dotenv::class, 'bootEnv')) {
//     (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
// }
(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}
