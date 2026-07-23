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
        math {
            direction: ltr !important;
            unicode-bidi: embed !important;
            text-align: initial !important;
        }
    </style>
</head>
<body>
    @php
        function getDirection($text) {
            $clean = strip_tags($text);
            // Remove all numbers, whitespace, punctuation, and symbols from the beginning
            $clean = preg_replace('/^[\d\s\p{P}\p{S}]+/u', '', $clean);
            return preg_match('/^[\p{Arabic}]/u', $clean) ? 'rtl' : 'ltr';
        }
    @endphp
    @foreach($questions as $question)
    @php
        $qDir = getDirection($question['text'] ?? '');
    @endphp
    <div class="question-container" dir="{{ $qDir }}">
        <div class="question-text">
            <bdi dir="{{ $qDir }}">{!! $question['text'] ?? 'Missing Question Text' !!}</bdi>
        </div>
        <ul class="options-list">
            @foreach($question['options'] ?? [] as $option)
                @php
                    $optDir = getDirection($option);
                @endphp
                <li class="option-item" dir="{{ $optDir }}">
                    <bdi dir="{{ $optDir }}">{!! $option !!}</bdi>
                </li>
            @endforeach
        </ul>
    </div>
    @endforeach
</body>
</html>
