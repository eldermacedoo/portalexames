{{-- layouts/app.blade.php --}}
@include('layouts.head')
{{-- Header branco com logo + menu central --}}
<header class="site-header">
  <div class="container d-flex align-items-center justify-content-between py-3">

    {{-- LOGO --}}
    <a href="{{ url('/') }}" class="d-flex align-items-center">
      <img src="{{ asset('img/platano.png') }}" alt="Platano" style="height:110px;">

    </a>

    {{-- MENU --}}
    <nav class="main-nav d-none d-lg-flex">
      <a href="#" class="nav-link">Procedimentos</a>
      <a href="#" class="nav-link">Fontes pagadoras</a>
      <a href="#" class="nav-link">Unidade de coleta</a>
      <a href="#" class="nav-link">Resultados</a>
    </nav>

    {{-- espaço à direita, vazio --}}
    <div class="d-none d-lg-block" style="width:80px;"></div>

  </div>
</header>

{{-- CONTEÚDO --}}
<div class="container mt-4">
  @yield('content')
</div>

@include('layouts.footer')