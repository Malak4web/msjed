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
        // ويندوز: OpenSSL محتاج ملف إعداد عشان تشفير إشعارات Push يشتغل
        if (($conf = env('OPENSSL_CONF')) && is_file($conf) && ! getenv('OPENSSL_CONF')) {
            putenv("OPENSSL_CONF={$conf}");
        }
    }
}
