<?php

namespace App\Providers;

use App\Contracts\DocumentToHtmlConverterInterface;
use App\Contracts\HtmlToPdfConverterInterface;
use App\Contracts\PdfPageCounterInterface;
use App\Contracts\PdfRasterizerInterface;
use App\Services\DocumentConverterService;
use App\Services\GhostscriptRasterizerService;
use App\Services\GotenbergClientService;
use App\Services\PdfPageCounterService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            HtmlToPdfConverterInterface::class,
            GotenbergClientService::class
        );

        $this->app->bind(
            DocumentToHtmlConverterInterface::class,
            DocumentConverterService::class
        );

        $this->app->bind(
            PdfPageCounterInterface::class,
            PdfPageCounterService::class
        );

        $this->app->bind(
            PdfRasterizerInterface::class,
            GhostscriptRasterizerService::class
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
