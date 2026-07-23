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
        function getDirection($text) {
            $clean = html_entity_decode($text ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $clean = strip_tags($clean);
            // Remove all numbers, whitespace, punctuation, and symbols from the beginning
            $clean = preg_replace('/^[\d\s\p{P}\p{S}\p{Z}\p{C}]+/u', '', $clean);
            // If string is empty after stripping, default to rtl (most safe for mixed exams where numbers are often Persian options)
            if (empty(trim($clean))) {
                return 'rtl'; // Fallback
            }
            return preg_match('/^[\p{Arabic}]/u', $clean) ? 'rtl' : 'ltr';
        }
        
        function sanitizeWysiwyg($text) {
            if (empty($text)) return '';
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
                @endphp
                <li class="option-item">
                    <bdi>{!! $optText !!}</bdi>
                </li>
            @endforeach
        </ul>
    </div>
    @endforeach
</body>
</html>
