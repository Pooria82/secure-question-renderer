<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Secure Exam Render</title>
    <style>
        body {
            font-family: 'Amiri', 'Noto Sans Arabic', Tahoma, Arial, sans-serif;
            background-color: white;
            color: black;
            padding: 40px;
            font-size: 16px;
            line-height: 1.6;
            margin: 0;
        }
        .question-container {
            border: 1px solid #ddd;
            padding: 20px;
            border-radius: 8px;
            background: #fff;
            margin-bottom: 30px;
            page-break-inside: avoid;
        }
        .question-text {
            font-weight: bold;
            margin-bottom: 15px;
            font-size: 18px;
        }
        .options-list {
            list-style-type: none;
            padding: 0;
            margin: 0;
        }
        .option-item {
            margin-bottom: 10px;
        }
        /* Isolate MathML formulas for LTR rendering */
        math, math * {
            direction: ltr !important;
            unicode-bidi: embed !important;
            text-align: initial !important;
        }
    </style>
</head>
<body>
    @php
        if (!function_exists('getDirection')) {
            function getDirection($text) {
                $clean = html_entity_decode($text ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $clean = strip_tags($clean);
                // For questions, remove all numbers, whitespace, punctuation, and symbols from the beginning
                $clean = preg_replace('/^[\d\s\p{P}\p{S}\p{Z}\p{C}]+/u', '', $clean);
                if (empty(trim($clean))) {
                    return 'rtl'; // Fallback
                }
                return preg_match('/^[\p{Arabic}]/u', $clean) ? 'rtl' : 'ltr';
            }
        }
        
        if (!function_exists('getOptionDirection')) {
            function getOptionDirection($text) {
                $clean = html_entity_decode($text ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
                // If it contains a math tag, it's formulaic
                if (stripos($clean, '<math') !== false) {
                    return 'ltr';
                }
                $clean = strip_tags($clean);
                // Only remove whitespace, punctuation, symbols. KEEP DIGITS!
                $clean = preg_replace('/^[\s\p{P}\p{S}\p{Z}\p{C}]+/u', '', $clean);
                
                if (empty(trim($clean))) {
                    return 'ltr'; // Fallback for pure symbols
                }
                // If it starts with an Arabic character or Persian digit, it's Persian/Arabic
                return preg_match('/^[\p{Arabic}]/u', $clean) ? 'rtl' : 'ltr';
            }
        }
        
        if (!function_exists('sanitizeWysiwyg')) {
            function sanitizeWysiwyg($text) {
                if (trim((string)$text) === '') return '';
                // Remove text-align and direction from style attributes
                $text = preg_replace('/(text-align|direction)\s*:\s*[^;"\']+[;]?/i', '', $text);
                // Remove dir="..." and align="..." attributes
                $text = preg_replace('/\s+(dir|align)=["\'][^"\']*["\']/i', '', $text);
                // Remove empty style attributes left behind
                $text = preg_replace('/\s+style=["\']\s*["\']/i', '', $text);
                // Remove empty p tags
                $text = preg_replace('/<p><\/p>/i', '', $text);
                return $text;
            }
        }
    @endphp
    @foreach($questions as $question)
    @php
        $qText = sanitizeWysiwyg($question['text'] ?? 'Missing Question Text');
        $qDir = getDirection($qText);
    @endphp
    <div class="question-container" dir="{{ $qDir }}">
        <div class="question-text">
            <bdi>{!! $qText !!}</bdi>
        </div>
        <ul class="options-list">
            @foreach($question['options'] ?? [] as $option)
                @php
                    $optText = sanitizeWysiwyg($option);
                    $optDir = getOptionDirection($optText);
                @endphp
                <li class="option-item" dir="{{ $optDir }}">
                    <bdi>{!! $optText !!}</bdi>
                </li>
            @endforeach
        </ul>
    </div>
    @endforeach
</body>
</html>
