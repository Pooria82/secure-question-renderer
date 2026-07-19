<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Question Render</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: white;
            color: black;
            padding: 40px;
            font-size: 18px;
            line-height: 1.6;
        }
        .question-container {
            border: 1px solid #ddd;
            padding: 20px;
            border-radius: 8px;
            background: #fff;
        }
        .question-text {
            font-weight: bold;
            margin-bottom: 20px;
            font-size: 22px;
        }
        .options-list {
            list-style-type: none;
            padding: 0;
        }
        .option-item {
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="question-container">
        <div class="question-text">
            {{ $question['text'] ?? 'Missing Question Text' }}
        </div>
        <ul class="options-list">
            @foreach($question['options'] ?? [] as $option)
                <li class="option-item">
                    &#x25CB; {{ $option }}
                </li>
            @endforeach
        </ul>
    </div>
</body>
</html>
