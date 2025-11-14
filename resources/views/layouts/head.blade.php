<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Google Font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap CSS (CDN) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Vite: seu CSS local + JS (apontar para o css se existir) -->
    {{-- se usa resources/css/app.css --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <title>@yield('title', 'Portal Exames')</title>

    <style>
        #osTable tbody tr { transition: background-color 0.2s; }
        #osTable tbody tr:hover { background-color: #f0f8ff; }
    </style>
</head>

<body>
