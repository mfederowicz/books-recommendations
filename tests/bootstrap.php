<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

if ((bool) $_SERVER['APP_DEBUG']) {
    umask(0000);
}
