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

<div class="container py-5">
    <div class="row gx-4 gy-4">
        <!-- INSTRUÇÕES / SIDEBAR -->
        <div class="col-lg-7">
            <div class="card sidebar-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <img src="{{ Vite::asset('resources/images/logo-platano.png') }}" alt="Logo" style="height:46px; object-fit:contain; margin-right:12px;">
                        <div>
                            <h5 class="mb-0" style="font-weight:600;">Portal Exames</h5>
                            <small class="text-muted">Consulta e gerenciamento de exames laboratoriais</small>
                        </div>
                    </div>

                    <hr>

                    <h6 class="mb-2">Instruções de acesso</h6>
                    <p class="text-muted mb-2">- <strong>Usuário</strong>: informe CPF, e-mail, código do pedido ou código existente no cartão do laboratório (ex: <code>P243</code>).</p>
                    <p class="text-muted mb-2">- <strong>Senha</strong>: utilize a senha disponibilizada pelo laboratório.</p>

                    <h6 class="mt-4 mb-2">Dicas</h6>
                    <ul class="text-muted" style="padding-left:1rem;">
                        <li>Se não recebeu senha, contate a recepção da unidade.</li>
                        <li>Confirme se o CPF foi informado sem pontos ou traços.</li>
                        <li>Em caso de erro, limpe o cache do navegador ou abra em modo anônimo.</li>
                    </ul>

                    <div class="mt-4">
                        <a href="{{ route('procedures.index') ?? '#' }}" class="btn btn-secondary-soft btn-sm">Ver procedimentos</a>
                        <a href="{{ route('contact') ?? '#' }}" class="btn btn-outline-secondary btn-sm ms-2">Precisa de ajuda?</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- CARD PRINCIPAL / WELCOME -->
        <div class="col-lg-5 d-flex">
            <div class="card login-card w-100">
                <div class="card-body">
                    <div class="text-center mb-3">
                        <h3 class="mb-1">Bem-vindo ao Portal de Exames 🧪</h3>
                        <p class="text-muted mb-0">Aqui você pode consultar e gerenciar seus exames laboratoriais.</p>
                    </div>

                    @if($userName)
                        <div class="alert alert-success d-flex align-items-start" role="alert">
                            <div class="me-2" style="font-size:1.25rem;">✅</div>
                            <div>
                                <div class="fw-semibold">Olá, {{ $userName }}.</div>
                                @if($tipoAcesso)
                                    <small class="text-muted">
                                        <i class="bi bi-person-badge"></i>
                                        Tipo de acesso: <strong>{{ $tipoAcesso }}</strong>
                                    </small>
                                @endif
                            </div>
                        </div>

                        <div class="d-grid gap-2 mt-3">
                            <a href="{{ route('dashboard') ?? '#' }}" class="btn btn-primary">Ir para o painel</a>
                            <form action="{{ route('logout') }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-outline-secondary">Sair</button>
                            </form>
                        </div>
                    @else
                        <div class="mb-3">
                            <p class="mb-2">Faça login para acessar seu painel.</p>
                            <a href="{{ route('login') }}" class="btn btn-primary w-100">Entrar</a>
                        </div>
                    @endif

                    @if(session('error'))
                        @php
                            $err = session('error');
                            if (is_array($err)) {
                                $err = implode(' - ', array_map(fn($v) => (string)$v, $err));
                            }
                        @endphp
                        <div class="alert alert-danger mt-3" role="alert">{{ $err }}</div>
                    @endif

                    <!-- espaço para mensagens flash adicionais -->
                    @if(session('status'))
                        <div class="alert alert-info mt-3">{{ session('status') }}</div>
                    @endif
                </div>

                <div class="card-footer text-muted small">
                    © {{ date('Y') }} Portal Exames. Todos os direitos reservados.
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
