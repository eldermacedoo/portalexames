<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Config;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function authenticate(Request $request)
    {
        $data = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        // Configurações via .env / config/services.php
        $soapUrl    = config('services.shift.url') ?? env('SHIFT_SOAP_URL');
        $soapAction = config('services.shift.action') ?? env('SHIFT_SOAP_LOGIN');

        if (empty($soapUrl) || empty($soapAction)) {
            return back()->with('error', 'Configuração do serviço SOAP ausente. Verifique .env')->withInput();
        }

        // Monta envelope SOAP (atenção ao encoding)
        $soapBody = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:shif="http://www.shift.com.br">
  <soapenv:Header/>
  <soapenv:Body>
    <shif:WsLoginUsuario>
      <shif:pUserId>{$this->xmlEscape($data['username'])}</shif:pUserId>
      <shif:pSenha>{$this->xmlEscape($data['password'])}</shif:pSenha>
      <shif:pSolicitante>?</shif:pSolicitante>
    </shif:WsLoginUsuario>
  </soapenv:Body>
</soapenv:Envelope>
XML;

        // Inicia cURL
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $soapUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: text/xml; charset=utf-8',
                'SOAPAction: "' . $soapAction . '"'
            ],
            CURLOPT_POSTFIELDS => $soapBody,
            // Em produção habilite as verificações SSL:
            // CURLOPT_SSL_VERIFYPEER => true,
            // CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_TIMEOUT => (int) (env('SOAP_TIMEOUT', 30)),
        ]);

        $response = curl_exec($curl);
        $curlErrNo = curl_errno($curl);
        $curlErr   = curl_error($curl);
        curl_close($curl);

        if ($curlErrNo) {
            // Não logar valores sensíveis
            Log::error("SOAP cURL error ({$curlErrNo}).");
            return back()->with('error', 'Erro ao comunicar com o serviço de autenticação.')->withInput();
        }

        if (empty($response)) {
            Log::warning('SOAP response vazio para tentativa de login.');
            return back()->with('error', 'Resposta vazia do serviço.')->withInput();
        }

        // Tenta converter o XML de resposta
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($response);
        if ($xml === false) {
            $errors = collect(libxml_get_errors())->map(fn($e) => $e->message)->implode('; ');
            Log::error("Erro ao parsear XML SOAP de login: {$errors}");
            return back()->with('error', 'Resposta inválida do serviço de autenticação.')->withInput();
        }

        // Detecta SOAP Faults (mais seguro)
        $faults = $xml->xpath('//*[local-name()="Fault"]');
        if (!empty($faults)) {
            // Não expor detalhes técnicos ao usuário, mas logar sem dados sensíveis
            Log::error('SOAP Fault recebido no login (ver logs).');
            return back()->with('error', 'Erro no serviço de autenticação.')->withInput();
        }

        // Registro de namespaces e busca resiliente pelo nó resultante
        $xml->registerXPathNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
        $xml->registerXPathNamespace('ns', 'http://www.shift.com.br');

        // Primeiro tenta o caminho com namespace; se falhar, usa local-name()
        $resultNodes = $xml->xpath('//ns:WsLoginUsuarioResult') ?: $xml->xpath('//*[local-name()="WsLoginUsuarioResult"]');

        if (!$resultNodes || count($resultNodes) === 0) {
            Log::warning('XPath WsLoginUsuarioResult não encontrado. Resposta SOAP (trunc): ' . substr($response, 0, 1000));
            return back()->with('error', 'Usuário ou senha inválidos.')->withInput();
        }

        $dados = $resultNodes[0];

        // Extrai campos com casting seguro e fallback
        $usuarioId   = isset($dados->usuarioWebId) ? (string)$dados->usuarioWebId : null;
        $userId      = isset($dados->usuarioWebUserId) ? (string)$dados->usuarioWebUserId : null;
        $nome        = isset($dados->usuarioWebNome) ? (string)$dados->usuarioWebNome : null;
        $sistema     = isset($dados->usuarioWebSistema) ? (string)$dados->usuarioWebSistema : null;
        $tipoUsuario = isset($dados->usuarioWebTipo) ? (int)$dados->usuarioWebTipo : null;

        // Verifique sucesso: ajuste conforme seu serviço real (talvez exista campo de erro)
        if (empty($usuarioId) && empty($userId)) {
            return back()->with('error', 'Credenciais inválidas.')->withInput();
        }

        // Mapeia tipo para texto
        $tipoTexto = match ($tipoUsuario) {
            1 => "Clínica / Hospital",
            2 => "Solicitante",
            3 => "Paciente Humano",
            4 => "Laboratório Apoiado",
            5 => "Usuário Administrador Shift LIS",
            default => "Tipo de usuário desconhecido",
        };

        // Armazena na sessão os dados essenciais do usuário.
        // Salva a senha criptografada para uso em chamadas SOAP subsequentes.
        // IMPORTANTE: a senha fica criptografada com APP_KEY do Laravel.
        session([
            'user' => [
                'usuarioId' => $usuarioId,
                'userId'    => $userId,
                'nome'      => $nome,
                'sistema'   => $sistema,
                'tipo'      => $tipoUsuario,
                'tipoText'  => $tipoTexto,
                'senha'     => Crypt::encryptString($data['password']),
            ]
        ]);

        // Redireciona conforme tipo (rotas devem existir)
        switch ($tipoUsuario) {
            case 1:
                return redirect()->route('clinica.home');
            case 2:
                return redirect()->route('solicitante.home');
            case 3:
                return redirect()->route('paciente.index');
            case 4:
                return redirect()->route('laboratorio.home');
            case 5:
                return redirect()->route('admin.home');
            default:
                return redirect()->route('home')->with('error', 'Tipo de usuário desconhecido.');
        }
    }

    public function logout(Request $request)
    {
        // Remover senha da sessão por segurança
        $request->session()->forget('user');
        return redirect()->route('login');
    }

    /**
     * Escapa caracteres especiais para inserir em XML.
     */
    private function xmlEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }
}
