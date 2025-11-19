<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\PacienteController;
use App\Http\Controllers\UnidadeController;

Route::get('/', fn() => redirect()->route('login'));
Route::get('/unidades', [UnidadeController::class, 'index'])->name('unidades.index');
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'authenticate'])->name('login.post');
Route::get('/logout', [LoginController::class, 'logout'])->name('logout');
Route::get('/home', function () {
    if (!session('user')) {
        return redirect()->route('login');
    }
    return view('home');
})->name('home');

Route::post('/soap-login', [PacienteController::class, 'soapLogin'])->name('paciente.soapLogin');
Route::post('/logout-paciente', [PacienteController::class, 'logout'])->name('paciente.logout');


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
