<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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

        // Configurações via .env (crie estas chaves no .env)
        $soapUrl    = config('services.shift.url') ?? env('SHIFT_SOAP_URL');
        $soapAction = config('services.shift.action') ?? env('SHIFT_SOAP_ACTION');

        if (empty($soapUrl) || empty($soapAction)) {
            return back()->with('error', 'Configuração do serviço SOAP ausente. Verifique .env')->withInput();
        }

        // Monta o envelope SOAP (atenção ao encoding)
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
            // Opcionalmente force SSL verification (recomendado em produção)
            // CURLOPT_SSL_VERIFYPEER => true,
            // CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($curl);
        $curlErrNo = curl_errno($curl);
        $curlErr   = curl_error($curl);
        curl_close($curl);

        if ($curlErrNo) {
            Log::error("SOAP cURL error ({$curlErrNo}): {$curlErr}");
            return back()->with('error', 'Erro ao comunicar com o serviço de autenticação.')->withInput();
        }

        if (empty($response)) {
            Log::warning('SOAP response vazio para usuário: ' . $data['username']);
            return back()->with('error', 'Resposta vazia do serviço.')->withInput();
        }

        // Tenta converter o XML de resposta
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($response);
        if ($xml === false) {
            $errors = collect(libxml_get_errors())->map(fn($e) => $e->message)->implode('; ');
            Log::error("Erro ao parsear XML SOAP: {$errors}");
            return back()->with('error', 'Resposta inválida do serviço de autenticação.')->withInput();
        }

        // Registra namespaces e busca pelo resultado
        $xml->registerXPathNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
        $xml->registerXPathNamespace('ns', 'http://www.shift.com.br');

        $resultNodes = $xml->xpath('//ns:WsLoginUsuarioResult');

        if (!$resultNodes || count($resultNodes) === 0) {
            Log::warning('XPath WsLoginUsuarioResult não encontrado. Resposta SOAP: ' . substr($response, 0, 1000));
            return back()->with('error', 'Usuário ou senha inválidos.')->withInput();
        }

        $dados = $resultNodes[0];

        // Extrai campos (use casting seguro)
        $usuarioId   = isset($dados->usuarioWebId)   ? (string)$dados->usuarioWebId   : null;
        $userId      = isset($dados->usuarioWebUserId) ? (string)$dados->usuarioWebUserId : null;
        $nome        = isset($dados->usuarioWebNome) ? (string)$dados->usuarioWebNome : null;
        $sistema     = isset($dados->usuarioWebSistema) ? (string)$dados->usuarioWebSistema : null;
        $tipoUsuario = isset($dados->usuarioWebTipo) ? (int)$dados->usuarioWebTipo : null;

        // Verifique se a resposta indica sucesso (ajuste conforme seu serviço)
        // Aqui assumimos que se houver userId/usuarioId então está ok; ajuste se houver um campo 'success' ou 'errorMessage'.
        if (empty($usuarioId) && empty($userId)) {
            return back()->with('error', 'Credenciais inválidas.')->withInput();
        }

        // Mapear tipo texto (mesma lógica do seu exemplo)
        $tipoTexto = match ($tipoUsuario) {
            1 => "Clínica / Hospital",
            2 => "Solicitante",
            3 => "Paciente Humano",
            4 => "Laboratório Apoiado",
            5 => "Usuário Administrador Shift LIS",
            default => "Tipo de usuário desconhecido",
        };

        // Armazena na sessão os dados essenciais do usuário (substitua por Auth se preferir)
        session([
            'user' => [
                'usuarioId' => $usuarioId,
                'userId'    => $userId,
                'nome'      => $nome,
                'sistema'   => $sistema,
                'tipo'      => $tipoUsuario,
                'tipoText'  => $tipoTexto,
            ]
        ]);

        return redirect()->intended('/home');
    }

    public function logout(Request $request)
    {
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
