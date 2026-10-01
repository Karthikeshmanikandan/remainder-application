<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Ensure cURL and OpenSSL use the trusted CA bundle on Windows environments where php.ini does not specify one
        if (! ini_get('curl.cainfo') || ! ini_get('openssl.cafile')) {
            $userProfile = getenv('USERPROFILE') ?: getenv('HOME');
            $candidatePaths = array_filter([
                getenv('CURL_CA_BUNDLE'),
                getenv('SSL_CERT_FILE'),
                $userProfile ? $userProfile.'/.cacert/cacert.pem' : null,
                $userProfile ? $userProfile.'\\.cacert\\cacert.pem' : null,
                'C:/Program Files/PHP/current/cacert.pem',
                'C:/Program Files/Git/usr/ssl/certs/ca-bundle.crt',
            ]);

            foreach ($candidatePaths as $path) {
                if (file_exists($path)) {
                    if (! ini_get('curl.cainfo')) {
                        ini_set('curl.cainfo', $path);
                    }
                    if (! ini_get('openssl.cafile')) {
                        ini_set('openssl.cafile', $path);
                    }
                    break;
                }
            }
        }
    }
}
