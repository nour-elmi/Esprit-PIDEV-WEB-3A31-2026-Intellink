<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}
<<<<<<< HEAD
=======

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}
>>>>>>> 1a1d1abfe95138a1bb4e3f2e2ec59080435548ae
