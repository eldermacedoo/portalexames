@extends('layouts.app')

@section('title', 'Login')

@section('content')
<div class="row justify-content-center">
  <div class="col-md-4">
    <div class="card mt-5">
      <div class="card-body">
        <h4 class="card-title text-center mb-4">Entrar</h4>

        {{-- mostra erros de sessão (aceita string ou array) --}}
        @if(session('error'))
          @php
            $err = session('error');
            if (is_array($err)) {
                $err = implode(' - ', array_map(function($v){
                    return (string)$v;
                }, $err));
            }
          @endphp
          <div class="alert alert-danger">{{ $err }}</div>
        @endif

        <form method="POST" action="{{ route('login.post') }}">
          @csrf

          <div class="mb-3">
            <label for="username" class="form-label">Usuário</label>
            @php
              $oldUsername = old('username');
              if (is_array($oldUsername)) {
                  // pega o primeiro valor caso venha como array
                  $oldUsername = count($oldUsername) ? (string)$oldUsername[0] : '';
              }
            @endphp
            <input id="username"
                   name="username"
                   type="text"
                   class="form-control @error('username') is-invalid @enderror"
                   value="{{ $oldUsername }}"
                   required autofocus>
            @error('username')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>

          <div class="mb-3">
            <label for="password" class="form-label">Senha</label>
            <input id="password"
                   name="password"
                   type="password"
                   class="form-control @error('password') is-invalid @enderror"
                   required>
            @error('password')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>

          <div class="d-grid gap-2">
            <button class="btn btn-primary" type="submit">Entrar</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection
