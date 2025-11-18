@extends('layouts.app')

@section('title', 'Login')

@if(session('user'))
    @php
        header("Location: " . route('paciente.index'));
        exit;
    @endphp
@endif

@section('content')
<div class="container py-4">
    <div class="row g-4">

        <!-- ESQUERDA — INSTRUÇÕES -->
        <div class="col-lg-7">

            <div class="help-card-wrap">
                <div class="help-card">
                    <!-- topo -->
                    <div class="card-top">
                        <h5 class="title">Instruções de acesso</h5>
                    </div>

                    <!-- conteúdo -->
                    <div class="card-section">
                        <!-- Pacientes (com fundo acinzentado como no exemplo) -->
                        <div class="section-pale mb-3">
                            <h6 class="section-heading">Pacientes</h6>
                            <p class="small">- Campo Usuário: é possível informar qualquer um dos dados: CPF, e-mail, código do pedido ou o código existente no cartão do laboratório. O código é formado pela letra <strong>P</strong> mais uma sequência de números.</p>
                            <p class="small"><span class="example">Exemplo: P243</span></p>
                            <p class="small">- Campo senha: Informe a senha disponibilizada para seu acesso.</p>
                        </div>

                        <!-- Solicitantes (fundo branco) -->
                        <div class="bottom">
                            <h6 class="section-heading">Solicitantes</h6>
                            <p class="small">- Campo Usuário: é possível informar qualquer um dos dados: CPF, e-mail ou número do conselho seguido de duas letras referente à sigla do seu estado. <span class="example">Exemplo: 12345SP</span></p>
                            <p class="small">- Campo senha: Informe a senha disponibilizada para seu acesso.</p>
                        </div>
                    </div>
                </div>
            </div>

        </div>


        <!-- DIREITA — LOGIN -->
        <div class="col-lg-5 d-flex justify-content-center" id="div-login">
            <div class="login-wrap w-100" style="max-width: 340px;">

                {{-- título FORA do card --}}
                <h4 class="login-title-main mb-3 text-center">Resultados de exames</h4>

                <div class="login-card w-100">

                    <!-- Cabeçalho: "Login" à esquerda e data à direita -->
                    <div class="card-header d-flex justify-content-between align-items-center px-3 py-2">
                        <span class="fw-semibold text-platano">Login</span>
                        <small class="text-muted" id="login-date"></small>
                    </div>

                    <div class="card-body p-3">

                        {{-- mensagens de erro --}}
                        @if(session('error'))
                        @php
                        $err = session('error');
                        if (is_array($err)) {
                        $err = implode(' - ', array_map(fn($v) => (string)$v, $err));
                        }
                        @endphp
                        <div class="alert alert-danger small">{{ $err }}</div>
                        @endif

                        <form method="POST" action="{{ route('login.post') }}">
                            @csrf

                            <div class="mb-2 d-flex align-items-center">
                                <label class="form-label fw-semibold me-2 mb-0" style="width:82px;">Usuário:</label>
                                <input type="text"
                                    name="username"
                                    class="form-control form-control-sm custom-input"
                                    value="{{ old('username') }}"
                                    required autofocus>
                            </div>

                            <div class="mb-2 d-flex align-items-center">
                                <label class="form-label fw-semibold me-2 mb-0" style="width:82px;">Senha:</label>
                                <input type="password"
                                    name="password"
                                    class="form-control form-control-sm custom-input"
                                    required>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mt-2">
                                <a href="#" class="text-muted small">Esqueci minha senha</a>
                                <div class="btn-row">
                                    <button type="submit" class="btn btn-login-platano btn-sm">Entrar</button>
                                </div>
                            </div>
                        </form>

                    </div>
                </div>
            </div>
        </div>

        <!-- script para data (mantém) -->
        <script>
            (function() {
                const el = document.getElementById('login-date');
                if (!el) return;
                const d = new Date();
                const dd = String(d.getDate()).padStart(2, '0');
                const mm = String(d.getMonth() + 1).padStart(2, '0');
                const yyyy = d.getFullYear();
                el.textContent = dd + '/' + mm + '/' + yyyy;
            })();
        </script>


    </div>
</div>
@endsection