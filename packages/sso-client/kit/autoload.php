<?php

/*
 * SSO client for applications that don't use Composer.
 * Copy the whole sso-client folder into your application, then:
 *
 *     require __DIR__ . '/sso-client/autoload.php';
 *     $user = \SsoClient\Gate::protect(__DIR__ . '/sso.php');
 *
 * Requires PHP 8.1+ with the openssl and curl extensions.
 */

spl_autoload_register(static function (string $class): void {
    $roots = [
        'SsoClient\\' => __DIR__.'/src/',
        'Firebase\\JWT\\' => __DIR__.'/php-jwt/src/',
    ];

    foreach ($roots as $prefix => $dir) {
        if (strncmp($class, $prefix, strlen($prefix)) === 0) {
            $file = $dir.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
            if (is_file($file)) {
                require $file;
            }

            return;
        }
    }
});
