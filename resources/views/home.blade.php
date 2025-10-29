@extends('layouts.app')

@section('title', 'Página Inicial')

@section('content')
@php
    $user = session('user');

    $userName = '';
    $tipoAcesso = '';

    if (is_array($user)) {
        $userName = $user['nome'] ?? '';
        $tipoAcesso = $user['tipoText'] ?? '';
    }
@endphp

<div class="text-center mt-5">
    <h1>Bem-vindo ao Portal de Exames 🧪</h1>

    @if($userName)
        <p class="lead">Olá, <strong>{{ $userName }}</strong>.</p>

        @if($tipoAcesso)
            <p class="text-muted">
                <i class="bi bi-person-badge"></i> Tipo de acesso: <strong>{{ $tipoAcesso }}</strong>
            </p>
        @endif

    @else
        <p class="lead">Faça login para acessar seu painel.</p>
    @endif

    <p>Aqui você pode consultar e gerenciar seus exames laboratoriais.</p>

    @if(session('error'))
        @php
            $err = session('error');
            if (is_array($err)) {
                $err = implode(' - ', array_map(fn($v) => (string)$v, $err));
            }
        @endphp
        <div class="alert alert-danger mt-3">{{ $err }}</div>
    @endif
</div>
@endsection
