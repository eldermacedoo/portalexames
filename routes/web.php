<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\PacienteController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'authenticate'])->name('login.post');
Route::get('/logout', [LoginController::class, 'logout'])->name('logout');


// rota de teste após login
Route::get('/home', function () {
    if (!session('user')) {
        return redirect()->route('login');
    }
    return view('home');
})->name('home');


Route::get('/paciente', function () { return view('paciente.index'); })->name('paciente.index');
Route::get('/pacientes/periodo', [PacienteController::class, 'listaPorPeriodo'])->name('paciente.lista');
Route::post('/soap-login', [PacienteController::class, 'soapLogin'])->name('paciente.soapLogin');
Route::get('/pacientes/os-abrir', [PacienteController::class, 'abrirOsPdf'])->name('pacientes.os.abrir');
