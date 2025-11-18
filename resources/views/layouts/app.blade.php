{{-- layouts/app.blade.php --}}
@include('layouts.head')

<style>
  /* estilos específicos do header para centralizar o menu sem sobreposição */
  .site-header {
    position: relative;
    z-index: 10;
  }

  .site-header .header-inner {
    position: relative;
    display: flex;
    align-items: center;
  }

  /* Centraliza o nav no desktop */
  .site-header .main-nav {
    position: absolute;
    left: 50%;
    transform: translateX(-50%);
    top: 50%;
    transform: translate(-50%, -50%);
    /* centraliza vertical + horizontal */
  }

  /* Garante que logo e user não sejam cobertos */
  .site-header .logo-wrapper {
    z-index: 20;
  }

  .site-header .user-wrapper {
    z-index: 20;
  }

  /* Pequeno ajuste para evitar sobreposição em larguras menores */
  @media (max-width: 991.98px) {
    .site-header .main-nav {
      position: static;
      transform: none;
      margin-left: 0;
      margin-right: 0;
    }

    .site-header .header-inner {
      justify-content: space-between;
    }
  }

  /* Ajustes do dropdown de usuário (form de senha) */
  .user-password-block .form-control-sm {
    height: calc(1.6rem + .6rem);
    padding: .25rem .5rem;
  }

  /* largura mínima do dropdown para caber os inputs */
  .user-dropdown-width {
    min-width: 300px;
    max-width: 360px;
  }

  /* mantém as mensagens compactas */
  .user-dropdown-width .alert {
    margin-bottom: .5rem;
    padding: .35rem .5rem;
    font-size: .85rem;
  }
</style>

<header class="site-header py-3">
  <div class="container d-flex align-items-center justify-content-between">

    {{-- LOGO --}}
    <a href="{{ url('/') }}" class="d-flex align-items-center logo-wrapper">
      <img src="{{ asset('img/Ativo2.png') }}" alt="Platano" class="logo-platano">
    </a>

    {{-- MENU CENTRAL + DROPDOWN DO USUÁRIO --}}
    <nav class="main-nav d-none d-lg-flex align-items-center" style="gap: 25px;">

      <a href="#" class="nav-link">Procedimentos</a>
      <a href="#" class="nav-link">Fontes pagadoras</a>
      <a href="#" class="nav-link">Unidade de coleta</a>
      <a href="{{ route('paciente.index') }}" class="nav-link">Resultados</a>

      {{-- Usuário / Dropdown simplificado --}}
      @if(session('user'))
      @php $nomeCompleto = session('user.usuarioWebNome') ?? session('user.nome') ?? 'Usuário'; @endphp

      <div class="dropdown ms-2 user-wrapper">
        <a id="userMenuToggle"
          class="nav-link dropdown-toggle d-flex align-items-center gap-2"
          href="#"
          role="button"
          data-bs-toggle="dropdown"
          aria-expanded="false"
          title="{{ $nomeCompleto }}">
          <i class="bi bi-person-circle" style="font-size: 18px;"></i>
          {{ \Illuminate\Support\Str::limit($nomeCompleto, 15) }}
        </a>

        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userMenuToggle">
          <li class="dropdown-item-text"><strong>Configurações</strong></li>
          <li>
            <hr class="dropdown-divider">
          </li>

          <li>
            <a href="#" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#alterarSenhaModal">
              Alterar senha
            </a>
          </li>

          <li>
            <hr class="dropdown-divider">
          </li>

          <li>
            <form action="{{ route('paciente.logout') }}" method="POST" class="d-inline">
              @csrf
              <button class="dropdown-item text-danger">Sair</button>
            </form>
          </li>
        </ul>
      </div>
      @endif



    </nav>

  </div>


</header>

<!-- Modal Alterar Senha -->
<div class="modal fade" id="alterarSenhaModal" tabindex="-1" aria-labelledby="alterarSenhaModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="alterarSenhaModalLabel">Alterar senha</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>

      <div class="modal-body">

        {{-- Mensagens do servidor --}}
        @if(session('password_error'))
        <div class="alert alert-danger small">{{ session('password_error') }}</div>
        @endif

        @if(session('password_success'))
        <div class="alert alert-success small">{{ session('password_success') }}</div>
        @endif

        @if($errors->any())
        <div class="alert alert-danger small">
          <ul class="mb-0">
            @foreach($errors->all() as $err)
            <li>{{ $err }}</li>
            @endforeach
          </ul>
        </div>
        @endif

        <form id="alterarSenhaForm" method="POST" action="{{ route('paciente.password.update') }}">
          @csrf

          <div class="mb-2">
            <label class="form-label small mb-1">Senha atual</label>
            <input type="password" name="current_password" class="form-control form-control-sm" required>
          </div>

          <div class="mb-2">
            <label class="form-label small mb-1">Nova senha</label>
            <input type="password" name="new_password" class="form-control form-control-sm" minlength="8" required>
          </div>

          <div class="mb-3">
            <label class="form-label small mb-1">Confirmar nova senha</label>
            <input type="password" name="new_password_confirmation" class="form-control form-control-sm" required>
          </div>

          <div class="d-flex justify-content-between align-items-center">
            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Fechar</button>
            <button type="submit" class="btn btn-success btn-sm">Alterar senha</button>
          </div>
        </form>

      </div>
    </div>
  </div>
</div>



{{-- CONTEÚDO --}}
<div class="container mt-4">
  @yield('content')
</div>

@include('layouts.footer')