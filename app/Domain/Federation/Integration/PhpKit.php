<?php

namespace App\Domain\Federation\Integration;

use App\Domain\Federation\Models\Application;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Finder\Finder;
use ZipArchive;

/**
 * A ready-to-copy PHP kit for one application: the SSO client (no Composer
 * needed), a filled-in sso.php and short instructions. The client secret is
 * never included; it is shown once when created and pasted in by hand.
 */
final class PhpKit
{
    public function build(Application $application): string
    {
        $connection = Connection::for($application);
        $client = base_path('vendor/tahir242/sso-client');
        $jwt = base_path('vendor/firebase/php-jwt');

        if (! is_dir($client.'/src') || ! is_dir($jwt.'/src')) {
            throw new RuntimeException('The tahir242/sso-client package is not installed.');
        }

        $path = storage_path('app/private/kits/'.Str::slug($application->slug).'-sso-kit-'.Str::random(8).'.zip');
        @mkdir(dirname($path), 0700, true);

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the kit.');
        }

        $zip->addFile($client.'/kit/autoload.php', 'sso-client/autoload.php');
        $this->addDirectory($zip, $client.'/src', 'sso-client/src');
        $this->addDirectory($zip, $jwt.'/src', 'sso-client/php-jwt/src');
        $zip->addFile($jwt.'/LICENSE', 'sso-client/php-jwt/LICENSE');
        $zip->addFromString('sso-client/.htaccess', "Require all denied\n");
        $zip->addFromString('sso.php', $this->config($connection, $application));
        $zip->addFromString('SSO-README.txt', $this->readme($connection));
        $zip->close();

        return $path;
    }

    private function addDirectory(ZipArchive $zip, string $from, string $to): void
    {
        foreach (Finder::create()->files()->in($from)->name('*.php') as $file) {
            $zip->addFile($file->getRealPath(), $to.'/'.str_replace('\\', '/', $file->getRelativePathname()));
        }
    }

    private function config(Connection $c, Application $application): string
    {
        $export = fn (string $value) => var_export($value, true);

        return <<<PHP
            <?php
            /*
             * single sign-on settings for {$application->name}.
             * Keep this file out of version control: it holds the client secret.
             */
            return [
                'issuer' => {$export($c->issuer)},
                'client_id' => {$export($c->clientId)},
                // Shown once by the SSO administrator (Applications → New client secret).
                'client_secret' => 'PASTE-THE-CLIENT-SECRET-HERE',

                // The page that calls Gate::protect(), plus ?sso=callback. Must be registered at the SSO.
                'redirect_uri' => {$export($c->gatePage().'?sso=callback')},
                'post_logout_redirect_uri' => {$export($c->postLogoutUri)},

                // Writable folder that the web server must not serve (keys and session data are cached here).
                'cache_dir' => __DIR__ . '/storage/sso',

                // Optional: only let in users with one of these roles in this application.
                'roles' => [],

                // Optional: also receive the phone number and/or postal address.
                // 'extra_scopes' => ['phone', 'address'],
            ];

            PHP;
    }

    private function readme(Connection $c): string
    {
        return <<<TXT
            Connecting {$c->appName} to single sign-on
            ================================================

            1. Copy the "sso-client" folder and "sso.php" into the application's root folder.
            2. Paste the client secret into sso.php (ask the SSO administrator for a new one).
            3. At the very top of every page, or of the file every page already includes:

                   require __DIR__ . '/sso-client/autoload.php';
                   \$user = \\SsoClient\\Gate::protect(__DIR__ . '/sso.php');

               \$user->userId, ->username, ->name, ->empId, ->empInitial, ->department, ->roles, ->hasRole('clerk')
               \$user->givenName, ->familyName, ->nickname, ->picture (photo URL), ->gender, ->birthdate
               \$user->phoneNumber, ->address (set 'extra_scopes' => ['phone', 'address'] in sso.php)

            4. Sign-out link:  <a href="<?= \\SsoClient\\Gate::logoutUrl() ?>">Sign out</a>

            These must be registered at the SSO (the administrator can do it in one click
            on the application's Connect page):

               Redirect URI             {$c->gatePage()}?sso=callback
               Back-channel logout URI  {$c->gatePage()}?sso=backchannel-logout
               Post-logout redirect     {$c->postLogoutUri}

            Needs PHP 8.1+ with the curl and openssl extensions.
            Full guide: {$c->issuer}/docs/integration/php
            TXT;
    }
}
