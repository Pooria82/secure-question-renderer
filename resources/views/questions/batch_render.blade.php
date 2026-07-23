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
            list-style-type: circle;
            list-style-position: outside;
            padding: 0;
            padding-inline-start: 30px;
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
    @foreach($questions as $question)
    <div class="question-container" dir="auto">
        <div class="question-text">
            {!! $question['text'] ?? 'Missing Question Text' !!}
        </div>
        <ul class="options-list">
            @foreach($question['options'] ?? [] as $option)
                <li class="option-item">
                    {!! $option !!}
                </li>
            @endforeach
        </ul>
    </div>
    @endforeach
</body>
</html>
