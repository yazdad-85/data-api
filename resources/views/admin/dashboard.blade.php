<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard Admin — Pusat Data</title>
    <style>
        body {
            margin: 0;
            font-family: Georgia, "Times New Roman", serif;
            background: linear-gradient(160deg, #e5e7eb 0%, #f9fafb 45%, #dbeafe 100%);
            color: #111827;
            min-height: 100vh;
            padding: 2rem;
        }
        .panel {
            max-width: 640px;
            background: #fff;
            border: 1px solid #d1d5db;
            padding: 1.5rem;
        }
        h1 { margin: 0 0 0.75rem; font-size: 1.5rem; }
        p { margin: 0.35rem 0; }
        form { margin-top: 1.25rem; }
        button {
            padding: 0.6rem 1rem;
            border: 0;
            background: #1f2937;
            color: #fff;
            font: inherit;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <main class="panel">
        <h1>Dashboard Admin</h1>
        <p>Nama: {{ $user->name }}</p>
        <p>Peran: {{ $user->role }}</p>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit">Keluar</button>
        </form>
    </main>
</body>
</html>
