@include('layouts.head')

<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
  <div class="container">
    <a class="navbar-brand" href="#">Portal Exames</a>
  </div>
</nav>

<div class="container mt-4">
  @yield('content')
</div>

@include('layouts.footer')
