<?php
spl_autoload_register(function ($class) {
    $prefixes = [
        'PHPMailer\\PHPMailer\\' => [
            __DIR__ . '/phpmailer/phpmailer/src/',
            __DIR__ . '/phpmailer/src/',
            __DIR__ . '/phpmailer/',
        ],
    ];
    foreach ($prefixes as $prefix => $dirs) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) continue;
        $relative = substr($class, $len);
        foreach ($dirs as $dir) {
            $file = $dir . str_replace('\\', '/', $relative) . '.php';
            if (file_exists($file)) {
                require $file;
                return true;
            }
        }
    }
    return false;
});

if (file_exists(__DIR__ . '/composer/autoload_real.php')) {
    require_once __DIR__ . '/composer/autoload_real.php';
}
