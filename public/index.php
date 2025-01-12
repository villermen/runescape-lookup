<?php

use App\Kernel;

// Let dev-server handle regular files.
if (php_sapi_name() === 'cli-server' && is_file(sprintf('%s/%s', __DIR__, $_SERVER['REQUEST_URI']))) {
    return false;
}

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
