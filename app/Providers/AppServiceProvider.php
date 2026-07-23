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
        $this->app->bind(
            \App\Contracts\HtmlToPdfConverterInterface::class,
            \App\Services\GotenbergClientService::class
        );

        $this->app->bind(
            \App\Contracts\DocumentToHtmlConverterInterface::class,
            \App\Services\DocumentConverterService::class
        );

        $this->app->bind(
            \App\Contracts\PdfPageCounterInterface::class,
            \App\Services\PdfPageCounterService::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
