<?php

declare(strict_types=1);

namespace App\Services;

class HtmlSanitizerService
{
    /**
     * Applies RTL, Bidi, and MathML fixes to the provided HTML content.
     */
    public function sanitize(string $htmlContent): string
    {
        // Enforce <html dir="rtl" lang="fa"> and <body dir="rtl"> tag attributes
        $htmlContent = preg_replace_callback('/<html([^>]*)>/i', function ($matches) {
            $attrs = $matches[1];
            $attrs = preg_replace('/\s*(dir|lang|xml:lang)=("[^"]*"|\'[^\']*\')/i', '', $attrs);

            return '<html'.$attrs.' dir="rtl" lang="fa">';
        }, $htmlContent);

        $htmlContent = preg_replace_callback('/<body([^>]*)>/i', function ($matches) {
            $attrs = $matches[1];
            if (stripos($attrs, 'dir=') === false) {
                return '<body'.$attrs.' dir="rtl">';
            }

            return $matches[0];
        }, $htmlContent);

        // Also enforce dir="rtl" on block elements
        $htmlContent = preg_replace_callback('/<(p|div|li|td|th)([^>]*)>/i', function ($matches) {
            $tag = $matches[1];
            $attrs = $matches[2];
            $attrs = preg_replace('/\s*dir=("[^"]*"|\'[^\']*\')/i', '', $attrs);

            return '<'.$tag.$attrs.' dir="rtl">';
        }, $htmlContent);

        // Isolate LTR text and skip <math> blocks
        $parts = preg_split('/(<math\b.*?>.*?<\/math>)/is', $htmlContent, -1, PREG_SPLIT_DELIM_CAPTURE);
        $htmlContent = '';
        $persianMathPattern = '/(?:<mi>\s*[\p{Arabic}\x{200C}]\s*<\/mi>\s*|<mspace\b[^>]*><\/mspace>\s*)+/u';

        foreach ($parts as $i => $part) {
            if ($i % 2 === 0) {
                $part = preg_replace_callback('/(>)([^<]+)(<)/', function ($matches) {
                    $text = $matches[2];
                    $text = preg_replace('/([a-zA-Z0-9][a-zA-Z0-9\(\)\=\+\-\*\/\.\, ]*[a-zA-Z0-9]|[a-zA-Z0-9])/i', '<span dir="ltr" style="unicode-bidi: embed;">$1</span>', $text);

                    return $matches[1].$text.$matches[3];
                }, $part);
            } else {
                $part = preg_replace_callback($persianMathPattern, function ($seqMatch) {
                    $sequence = $seqMatch[0];
                    if (! preg_match('/[\p{Arabic}]/u', $sequence)) {
                        return $sequence;
                    }
                    $text = preg_replace('/<mi>\s*([\p{Arabic}\x{200C}])\s*<\/mi>/u', '$1', $sequence);
                    $text = preg_replace('/<mspace\b[^>]*><\/mspace>/u', ' ', $text);
                    $text = preg_replace('/\s+/', ' ', $text);
                    $text = trim($text);

                    return '<mtext dir="rtl" style="font-family: \'Amiri\', sans-serif;">'.$text.'</mtext>';
                }, $part);
            }
            $htmlContent .= $part;
        }

        // Convert WMF/EMF images to PNG
        $htmlContent = preg_replace_callback('/data:image\/(x-wmf|wmf|x-emf|emf);base64,([a-zA-Z0-9+\/=\s]+)/i', function ($matches) {
            $base64 = preg_replace('/\s+/', '', $matches[2]);
            $data = base64_decode($base64);
            $tmpName = uniqid('img_');
            $ext = str_replace('x-', '', strtolower($matches[1]));
            $wmfFile = '/tmp/'.$tmpName.'.'.$ext;
            $pngFile = '/tmp/'.$tmpName.'.png';
            file_put_contents($wmfFile, $data);
            exec("convert {$wmfFile} {$pngFile} 2>&1", $out, $ret);
            if (file_exists($pngFile) && filesize($pngFile) > 0) {
                $pngData = file_get_contents($pngFile);
                $newBase64 = base64_encode($pngData);
                @unlink($wmfFile);
                @unlink($pngFile);

                return 'data:image/png;base64,'.$newBase64;
            }
            @unlink($wmfFile);
            @unlink($pngFile);

            return $matches[0];
        }, $htmlContent);

        return $this->injectCss($htmlContent);
    }

    private function injectCss(string $htmlContent): string
    {
        $customCss = <<<'CSS'
<style>
    html, body {
        max-width: 100% !important; margin: 0 !important; padding: 20px !important;
        font-family: 'Amiri', 'Noto Sans Arabic', sans-serif !important;
        direction: rtl !important; text-align: right !important; overflow: visible !important;
    }
    html, body, p, div, span, table, td, th, h1, h2, h3, h4, h5, h6, li, section, article { 
        direction: rtl !important; text-align: right !important; overflow: visible !important;
    }
    img { max-width: 100% !important; height: auto !important; display: inline-block !important; }
    table { width: 100% !important; display: table !important; overflow: visible !important; margin-left: auto !important; margin-right: 0 !important; text-align: right !important; }
    tr { page-break-inside: avoid !important; }
    math { direction: ltr !important; unicode-bidi: embed !important; text-align: initial !important; max-width: 100%; overflow: visible !important; }
    annotation { display: none !important; }
    pre, code, .sourceCode { direction: ltr !important; text-align: left !important; overflow: visible !important; white-space: pre-wrap !important; }
</style>
CSS;

        return str_replace('</head>', $customCss."\n</head>", $htmlContent);
    }
}
