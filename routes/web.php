<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\PacienteController;



Route::get('/', fn() => redirect()->route('login'));

// Formulário de login (GET)
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');

// Processa o login (POST)
Route::post('/login', [LoginController::class, 'authenticate'])->name('login.post');

// Logout (padrão app) - se seu LoginController espera GET, mantive GET,
// mas idealmente deveria ser POST. Mantenha conforme já implementado.
Route::get('/logout', [LoginController::class, 'logout'])->name('logout');

// Home — protege via checagem manual de sessão (mantive sua lógica)
Route::get('/home', function () {
    if (!session('user')) {
        return redirect()->route('login');
    }
    return view('home');
})->name('home');


/*
|--------------------------------------------------------------------------
| Rotas do módulo Paciente
|--------------------------------------------------------------------------
|
| Observações:
| - Não alterei nomes de métodos do PacienteController.
| - A view de login (form) continua sendo servida por LoginController@showLoginForm (rota 'login').
| - O endpoint SOAP de autenticação do paciente fica público (POST /soap-login) para receber o form.
|
*/

// Endpoint que recebe o formulário de login do paciente (soap-login)
Route::post('/soap-login', [PacienteController::class, 'soapLogin'])->name('paciente.soapLogin');

// Logout específico do módulo paciente (opcional)
Route::post('/logout-paciente', [PacienteController::class, 'logout'])->name('paciente.logout');


/*
| Rotas protegidas do módulo paciente
| Aplicar o middleware personalizado 'pacienteAuth' (criado e registrado em Kernel)
*/
Route::middleware(['pacienteAuth'])->group(function () {

    Route::get('/paciente', function () {
        return view('paciente.index');
    })->name('paciente.index');

    Route::get('/pacientes/periodo', [PacienteController::class, 'listaPorPeriodo'])->name('paciente.lista');

    Route::get('/paciente/os-abrir', [PacienteController::class, 'abrirOsPdf'])->name('paciente.os.abrir');

    Route::get('/paciente/pdf-feedback', [PacienteController::class, 'pdfFeedback'])->name('paciente.pdf-feedback');

    Route::post('/paciente/feedback', [PacienteController::class, 'salvarFeedback'])->name('paciente.salvarFeedback');

    Route::get('/paciente/pdf-clean', [PacienteController::class, 'pdfFeedbackClean'])->name('paciente.pdf-clean');

    Route::post('/paciente/alterar-senha', [PacienteController::class, 'alterarSenha'])
        ->name('paciente.password.update');
});
