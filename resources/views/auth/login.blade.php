@extends('layouts.app')

@section('title', 'Login')

@section('content')
<div class="container py-4">
    <div class="row g-4">

        <!-- ESQUERDA — INSTRUÇÕES -->
        <div class="col-lg-7">
            <div class="sidebar-card">
                <h5 class="mb-3 fw-semibold">Instruções de acesso</h5>

                <h6 class="fw-semibold">Pacientes</h6>
                <p>
                    - Campo Usuário: é possível informar qualquer um dos dados: CPF, e-mail, código do pedido
                    ou o código existente no cartão do laboratório. O código é formado pela letra P mais uma sequência de números.<br>
                    <strong>Exemplo:</strong> P243
                </p>

                <p>- Campo senha: Informe a senha disponibilizada para seu acesso.</p>

                <h6 class="fw-semibold mt-4">Solicitantes</h6>
                <p>
                    - Campo Usuário: é possível informar qualquer um dos dados: CPF, e-mail ou número do conselho
                    seguido de duas letras referente à sigla do seu estado.<br>
                    <strong>Exemplo:</strong> 12345SP
                </p>

                <p>- Campo senha: Informe a senha disponibilizada para seu acesso.</p>
            </div>
        </div>

        <!-- DIREITA — LOGIN -->
        <div class="col-lg-5 d-flex justify-content-center">
            <div class="login-card w-100" style="max-width: 380px;">

                <!-- Cabeçalho igual ao Plátano -->
                <div class="card-header">
                    <span class="fw-semibold">Login</span>                    
                </div>

                <div class="card-body">

                    {{-- título --}}
                    <h4 class="login-title-main mb-3">Resultados de exames</h4>

                    {{-- mensagens de erro --}}
                    @if(session('error'))
                        @php
                            $err = session('error');
                            if (is_array($err)) {
                                $err = implode(' - ', array_map(fn($v) => (string)$v, $err));
                            }
                        @endphp
                        <div class="alert alert-danger">{{ $err }}</div>
                    @endif

                    <form method="POST" action="{{ route('login.post') }}">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Usuário:</label>
                            <input type="text"
                                   name="username"
                                   class="form-control"
                                   value="{{ old('username') }}"
                                   required autofocus>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Senha:</label>
                            <input type="password"
                                   name="password"
                                   class="form-control"
                                   required>
                        </div>

                        <a href="#" class="text-muted small">Esqueci minha senha</a>

                        <div class="btn-row mt-3">
                            <button type="submit" class="btn-login-platano">Entrar</button>
                        </div>
                    </form>
                </div>

            </div>
        </div>

    </div>
</div>
@endsection
