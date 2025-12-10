<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

if (isset($_SERVER['APP_DEBUG']) && (bool) $_SERVER['APP_DEBUG']) {
    umask(0000);
}
