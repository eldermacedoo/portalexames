<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class PacienteAuth
{
    public function handle(Request $request, Closure $next)
    {
        // checa a sessão que seu controller já utiliza
        $sessionUser = session('user');

        if (!$sessionUser || !isset($sessionUser['userId']) || !isset($sessionUser['senha'])) {
            // redireciona para a view de login (rota nomeada 'login' abaixo)
            return redirect()->route('login')->with('error', 'Favor realize login novamente.');
        }

        return $next($request);
    }
}
