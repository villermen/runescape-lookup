<?php

use App\Kernel;

// Let dev-server handle regular files.
if (php_sapi_name() === 'cli-server') {
    if (preg_match('~^(/[^#?]+)~', $_SERVER['REQUEST_URI'], $matches)) {
        if (is_file(__DIR__ . $matches[1])) {
            return false;
        }
    }
}

require_once dirname(__DIR__) . '/vendor/autoload_runtime.php';

return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
