<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <style>
        @font-face {
            font-family: 'DejaVu Sans';
            src: url('{{ storage_path('fonts/DejaVuSans.ttf') }}') format('truetype');
        }

        * {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10px;
        }

        body {
            direction: rtl;
            margin: 0;
            padding: 20px;
        }

        h1 {
            text-align: center;
            font-size: 18px;
            margin-bottom: 5px;
            color: #1e293b;
        }

        .generated-at {
            text-align: center;
            font-size: 8px;
            color: #64748b;
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th {
            background-color: #f1f5f9;
            color: #1e293b;
            font-weight: bold;
            padding: 8px 6px;
            text-align: right;
            border: 1px solid #e2e8f0;
        }

        td {
            padding: 6px;
            border: 1px solid #e2e8f0;
            text-align: right;
        }

        tr:nth-child(even) {
            background-color: #f8fafc;
        }

        .numeric {
            text-align: left;
            direction: ltr;
        }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <p class="generated-at">تم الإنشاء: {{ $generatedAt }}</p>

    <table>
        <thead>
            <tr>
                @foreach($headings as $heading)
                    <th>{{ $heading }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
                <tr>
                    @foreach($row as $cell)
                        <td>{{ is_numeric($cell) ? $cell : ($cell ?? '') }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
