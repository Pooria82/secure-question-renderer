<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Question Render</title>
    <style>
        body {
            font-family: 'Amiri', 'Noto Sans Arabic', Tahoma, Arial, sans-serif;
            background-color: white;
            color: black;
            padding: 40px;
            font-size: 24px; /* Slightly larger for clearer rendering */
            line-height: 1.6;
            margin: 0;
        }
        .question-container {
            border: 1px solid #ddd;
            padding: 30px;
            border-radius: 8px;
            background: #fff;
        }
        .question-text {
            font-weight: bold;
            margin-bottom: 25px;
            font-size: 28px;
        }
        .options-list {
            list-style-type: none;
            padding: 0;
            margin: 0;
        }
        .option-item {
            margin-bottom: 15px;
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
    <div class="question-container">
        <!-- dir="auto" will automatically align right for Persian and left for English -->
        <div class="question-text" dir="auto">
            {!! $question['text'] ?? 'Missing Question Text' !!}
        </div>
        <ul class="options-list">
            @foreach($question['options'] ?? [] as $option)
                <li class="option-item" dir="auto">
                    &#x25CB; {!! $option !!}
                </li>
            @endforeach
        </ul>
    </div>
</body>
</html>
