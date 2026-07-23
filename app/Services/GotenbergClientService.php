<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\RenderFailureException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class GotenbergClientService
{
    /**
     * Converts HTML string to PDF using Gotenberg.
     *
     * @throws RenderFailureException
     */
    public function convertHtmlToPdf(string $htmlContent, string $pdfPath): void
    {
        $gotenbergEndpoint = $this->resolveGotenbergEndpoint();

        try {
            $response = Http::timeout(600)->attach(
                'files', $htmlContent, 'index.html'
            )->post($gotenbergEndpoint, [
                'marginTop' => 0,
                'marginBottom' => 0,
                'marginLeft' => 0,
                'marginRight' => 0,
                'waitDelay' => '2s',
                'preferCSSPageSize' => true,
                'printBackground' => true,
            ]);
        } catch (ConnectionException $e) {
            if ($gotenbergEndpoint !== 'http://localhost:3000/forms/chromium/convert/html') {
                try {
                    $response = Http::timeout(600)->attach(
                        'files', $htmlContent, 'index.html'
                    )->post('http://localhost:3000/forms/chromium/convert/html', [
                        'marginTop' => 0,
                        'marginBottom' => 0,
                        'marginLeft' => 0,
                        'marginRight' => 0,
                        'waitDelay' => '2s',
                        'preferCSSPageSize' => true,
                        'printBackground' => true,
                    ]);
                } catch (ConnectionException $fallbackEx) {
                    throw new RenderFailureException('Gotenberg fallback connection failed: '.$fallbackEx->getMessage(), 0, $fallbackEx);
                }
            } else {
                throw new RenderFailureException('Gotenberg connection failed: '.$e->getMessage(), 0, $e);
            }
        }

        if ($response->successful()) {
            file_put_contents($pdfPath, $response->body());
        } else {
            throw new RenderFailureException('Gotenberg Chromium conversion failed: '.$response->body());
        }

        if (! file_exists($pdfPath)) {
            throw new RenderFailureException('Gotenberg Chromium did not produce the expected PDF file.');
        }
    }

    private function resolveGotenbergEndpoint(): string
    {
        $gotenbergUrl = config('secure-pdf.gotenberg.url');
        $gotenbergHost = config('secure-pdf.gotenberg.host');

        if (! empty($gotenbergUrl)) {
            $gotenbergEndpoint = str_contains((string) $gotenbergUrl, '/forms/')
                ? (string) $gotenbergUrl
                : rtrim((string) $gotenbergUrl, '/').'/forms/chromium/convert/html';
            if (! str_starts_with($gotenbergEndpoint, 'http://') && ! str_starts_with($gotenbergEndpoint, 'https://')) {
                $gotenbergEndpoint = 'http://'.$gotenbergEndpoint;
            }

            return $gotenbergEndpoint;
        }

        if (! empty($gotenbergHost)) {
            $host = (string) $gotenbergHost;
            if (! str_starts_with($host, 'http://') && ! str_starts_with($host, 'https://')) {
                $host = 'http://'.$host;
            }
            if (! preg_match('/:\d+$/', parse_url($host, PHP_URL_HOST) ?? parse_url($host, PHP_URL_PATH) ?? '') && ! str_contains(substr($host, 7), ':')) {
                $host = rtrim($host, '/').':3000';
            }

            return rtrim($host, '/').'/forms/chromium/convert/html';
        }

        $endpoint = 'http://gotenberg:3000/forms/chromium/convert/html';
        if (@gethostbyname('gotenberg') === 'gotenberg') {
            $endpoint = 'http://localhost:3000/forms/chromium/convert/html';
        }

        return $endpoint;
    }
}
