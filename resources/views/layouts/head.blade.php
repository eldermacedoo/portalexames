<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Portal Exames')</title>
    <style>
        #osTable tbody tr {
            transition: background-color 0.2s;
        }

        #osTable tbody tr:hover {
            background-color: #f0f8ff;
        }
    </style>
    @vite(['resources/js/app.js'])
</head>

<body>