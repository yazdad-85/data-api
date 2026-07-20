<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Pusat Data')</title>
    <style>
        :root {
            --bg: #f3f4f6;
            --card: #ffffff;
            --text: #111827;
            --muted: #6b7280;
            --border: #d1d5db;
            --accent: #1f2937;
            --danger: #b91c1c;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            font-family: Georgia, "Times New Roman", serif;
            background: linear-gradient(160deg, #e5e7eb 0%, #f9fafb 45%, #dbeafe 100%);
            color: var(--text);
        }
        .shell {
            width: min(420px, calc(100% - 2rem));
            background: var(--card);
            border: 1px solid var(--border);
            padding: 2rem;
        }
        h1 {
            margin: 0 0 0.5rem;
            font-size: 1.5rem;
        }
        p {
            margin: 0 0 1.25rem;
            color: var(--muted);
        }
        label {
            display: block;
            margin-bottom: 0.35rem;
            font-size: 0.95rem;
        }
        input {
            width: 100%;
            margin-bottom: 1rem;
            padding: 0.65rem 0.75rem;
            border: 1px solid var(--border);
            font: inherit;
        }
        button {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 0;
            background: var(--accent);
            color: #fff;
            font: inherit;
            cursor: pointer;
        }
        .error {
            margin: 0 0 1rem;
            color: var(--danger);
            font-size: 0.95rem;
        }
    </style>
</head>
<body>
    <main class="shell">
        @yield('content')
    </main>
</body>
</html>
