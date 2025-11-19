<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use App\Models\FeedbackPaciente;

class PacienteController extends Controller
{
    private string $soapUrl = 'https://portal.laboratorioplatano.com.br:443/shift/lis/platano/elis/s01.util.b2b.shift.consultas.Webserver.cls';
    private string $soapNs  = 'http://www.shift.com.br';

    public function listaPorPeriodo(Request $request)
    {
        $inicio = $request->query('inicio', date('Y-m-01'));
        $fim    = $request->query('fim', date('Y-m-d'));

        if (!\DateTime::createFromFormat('Y-m-d', $inicio) || !\DateTime::createFromFormat('Y-m-d', $fim)) {
            return response()->json(['error' => 'Formato inválido. Use YYYY-MM-DD'], 422);
        }

        $sessionUser = session('user');
        if (!$sessionUser || !isset($sessionUser['userId']) || !isset($sessionUser['senha'])) {
            return response()->json(['error' => 'Usuário não autenticado (session user).'], 401);
        }

        $pacienteUserId = $sessionUser['userId'];
        try {
            $senha = Crypt::decryptString($sessionUser['senha']);
        } catch (\Throwable $e) {
            $senha = $sessionUser['senha'] ?? null;
        }

        $soapEnvelope = <<<XML
            <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:shif="{$this->soapNs}">
            <soapenv:Header/>
            <soapenv:Body>
                <shif:WsGetListaExPacienteByPeriodo>
                <shif:pPacienteUserId>{$pacienteUserId}</shif:pPacienteUserId>
                <shif:pSenha>{$senha}</shif:pSenha>
                <shif:pPeriodoInicio>{$inicio}</shif:pPeriodoInicio>
                <shif:pPeriodoFinal>{$fim}</shif:pPeriodoFinal>
                <shif:pEmitirLaudoComparativo>false</shif:pEmitirLaudoComparativo>
                </shif:WsGetListaExPacienteByPeriodo>
            </soapenv:Body>
            </soapenv:Envelope>
            XML;

        $soapCookie = session('soap_cookie') ?? '';

        $headersVariants = [[
            'SOAPAction'  => "{$this->soapNs}/s01.util.b2b.shift.consultas.Webserver.WsGetListaExPacienteByPeriodo",
            'Content-Type' => 'text/xml; charset=utf-8',
        ]];

        $attempts = [];
        $final = [];

        foreach ($headersVariants as $variant) {
            $curlHeaders = [];
            foreach ($variant as $k => $v) $curlHeaders[] = "{$k}: {$v}";
            if (!empty($soapCookie)) $curlHeaders[] = 'Cookie: ' . $soapCookie;

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $this->soapUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $soapEnvelope,
                CURLOPT_HTTPHEADER => $curlHeaders,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_TIMEOUT => 60,
            ]);

            $resp = curl_exec($ch);
            $errNo = curl_errno($ch);
            $err   = curl_error($ch);
            $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $snippet = is_string($resp) ? mb_substr($resp, 0, 4000) : '';

            $attempt = [
                'headers_sent' => $variant,
                'cookie_sent'  => !empty($soapCookie),
                'http_status'  => $httpStatus,
                'curl_errno'   => $errNo,
                'curl_error'   => $err,
                'response_snippet' => $snippet,
                'found_count'  => 0,
                'found_sample' => []
            ];

            if ($errNo) {
                $attempt['note'] = 'cURL error';
                $attempts[] = $attempt;
                continue;
            }

            $found = [];
            if (is_string($resp) && trim($resp) !== '') {
                libxml_use_internal_errors(true);
                $dom = new \DOMDocument();
                $loaded = @$dom->loadXML($resp);

                if ($loaded) {
                    $xpath = new \DOMXPath($dom);
                    $osNodes = $xpath->query("//*[local-name() = 'os']");

                    foreach ($osNodes as $osNode) {
                        $numNodeList = $xpath->query("./*[local-name() = 'osNumero']", $osNode);
                        if (!$numNodeList || $numNodeList->length === 0) continue;
                        $osNumero = trim((string) $numNodeList->item(0)->textContent);
                        $dateNodeList = $xpath->query("./*[local-name() = 'data']", $osNode);
                        $data = null;
                        if ($dateNodeList && $dateNodeList->length > 0) {
                            $dateStr = trim((string) $dateNodeList->item(0)->textContent);
                            $d = \DateTime::createFromFormat('Y-m-d', $dateStr);
                            if ($d && $d->format('Y-m-d') === $dateStr) $data = $dateStr;
                            else {
                                try {
                                    $d2 = new \DateTime($dateStr);
                                    $data = $d2->format('Y-m-d');
                                } catch (\Throwable $e) {
                                    $data = null;
                                }
                            }
                        }

                        $statusNodeList = $xpath->query("./*[local-name() = 'status']", $osNode);
                        $status = null;
                        if ($statusNodeList && $statusNodeList->length > 0) {
                            $s = trim((string) $statusNodeList->item(0)->textContent);
                            $status = ($s === '') ? null : $s;
                        }
                        $mnemonicos = [];
                        $mnQuerySpecific = "./*[local-name() = 'listaProcedimento']/*[local-name() = 'osProcedimento']/*[local-name() = 'mnemonico']";
                        $mnNodes = $xpath->query($mnQuerySpecific, $osNode);

                        if ($mnNodes && $mnNodes->length > 0) {
                            foreach ($mnNodes as $mn) {
                                $val = trim((string) $mn->textContent);
                                if ($val !== '') $mnemonicos[] = $val;
                            }
                        } else {
                            $mnNodesBroad = $xpath->query(".//*[local-name() = 'mnemonico']", $osNode);
                            if ($mnNodesBroad && $mnNodesBroad->length > 0) {
                                foreach ($mnNodesBroad as $mn) {
                                    $accept = false;
                                    $p = $mn->parentNode;
                                    while ($p && $p->nodeType === XML_ELEMENT_NODE) {
                                        $lname = $p->localName ?? $p->nodeName;
                                        if (in_array(strtolower($lname), ['listaprocedimento', 'osprocedimento'])) {
                                            $accept = true;
                                            break;
                                        }
                                        $p = $p->parentNode;
                                    }
                                    if ($accept) {
                                        $val = trim((string) $mn->textContent);
                                        if ($val !== '') $mnemonicos[] = $val;
                                    }
                                }
                            }
                        }

                        if (empty($mnemonicos)) {
                            $nameNodes = $xpath->query("./*[local-name() = 'listaProcedimento']/*[local-name() = 'osProcedimento']/*[local-name() = 'nome']", $osNode);
                            if ($nameNodes && $nameNodes->length > 0) {
                                foreach ($nameNodes as $n) {
                                    $v = trim((string) $n->textContent);
                                    if ($v !== '') $mnemonicos[] = $v;
                                }
                            }
                        }

                        $mnemonicos = array_values(array_unique($mnemonicos));

                        $debugSnippet = null;
                        if (empty($mnemonicos)) {
                            try {
                                $xmlFragment = $dom->saveXML($osNode);
                                $debugSnippet = is_string($xmlFragment) ? mb_substr($xmlFragment, 0, 1000) : null;
                            } catch (\Throwable $e) {
                                $debugSnippet = null;
                            }
                        }

                        $found[] = [
                            'osNumero'   => $osNumero,
                            'data'       => $data,
                            'status'     => $status,
                            'mnemonicos' => $mnemonicos,
                            'debug'      => $debugSnippet,
                        ];
                    }
                } else {
                    $attempt['note'] = 'XML load failed';
                }
            }

            $attempt['found_count']  = count($found);
            $attempt['found_sample'] = array_slice($found, 0, 10);
            $attempts[] = $attempt;

            if (!empty($found)) {
                $final = $found;
                break;
            }
        }

        if (empty($final)) {
            return response()->json([
                'success'  => false,
                'message'  => 'Nenhuma O.S. encontrada',
                'attempts' => $attempts
            ], 200);
        }

        return response()->json([
            'success' => true,
            'osNumeros' => $final,
            'attempts_summary' => array_map(fn($a) => [
                'headers_sent' => $a['headers_sent'],
                'http_status'  => $a['http_status'],
                'found_count'  => $a['found_count'],
            ], $attempts),
        ]);
    }

    public function abrirOsPdf(Request $request)
    {
        $osNumero = $request->query('osNumero');
        $emitir   = $request->query('emitir', 'false');

        if (!$osNumero) return response('Parâmetro osNumero é obrigatório', 400);

        $sessionUser = session('user');
        if (!$sessionUser || !isset($sessionUser['userId']) || !isset($sessionUser['senha'])) {
            return response('Usuário não autenticado (session user).', 401);
        }

        $pacienteUserId = $sessionUser['userId'];
        try {
            $senha = Crypt::decryptString($sessionUser['senha']);
        } catch (\Throwable $e) {
            $senha = $sessionUser['senha'] ?? null;
        }

        $soapEnvelope = <<<XML
            <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:shif="{$this->soapNs}">
            <soapenv:Header/>
            <soapenv:Body>
                <shif:WsGetListaExPaciente>
                <shif:pPacienteUserId>{$pacienteUserId}</shif:pPacienteUserId>
                <shif:pSenha>{$senha}</shif:pSenha>
                <shif:pCodigoOs>{$osNumero}</shif:pCodigoOs>
                <shif:pEmitirLaudoComparativo>{$emitir}</shif:pEmitirLaudoComparativo>
                </shif:WsGetListaExPaciente>
            </soapenv:Body>
            </soapenv:Envelope>
            XML;

        $curlHeaders = [
            'SOAPAction: ' . "{$this->soapNs}/s01.util.b2b.shift.consultas.Webserver.WsGetListaExPaciente",
            'Content-Type: text/xml; charset=utf-8',
        ];
        if ($cookie = session('soap_cookie')) $curlHeaders[] = 'Cookie: ' . $cookie;

        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->soapUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $soapEnvelope,
            CURLOPT_HTTPHEADER => $curlHeaders,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => 60,
        ]);
        $resp = curl_exec($ch);
        $errNo = curl_errno($ch);
        $err   = curl_error($ch);
        $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errNo) return response("cURL error ao chamar SOAP: {$err}", 502);
        if (!is_string($resp) || trim($resp) === '') {
            return response("Resposta vazia do serviço SOAP (status: {$httpStatus})", 502);
        }

        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        if (!@$dom->loadXML($resp)) {
            return response("Falha ao parsear XML SOAP. Preview: " . mb_substr($resp, 0, 2000), 502);
        }
        $xpath = new \DOMXPath($dom);
        $nodes = $xpath->query("//*[local-name() = 'ListaOS']/*[local-name() = 'os']/*[local-name() = 'urlPdf']");
        $urlPdf = null;
        if ($nodes && $nodes->length > 0) $urlPdf = trim((string) $nodes->item(0)->textContent);
        if (!$urlPdf) {
            $alt = $xpath->query("//*[local-name() = 'urlPdf']");
            if ($alt && $alt->length > 0) $urlPdf = trim((string) $alt->item(0)->textContent);
        }
        if (!$urlPdf) return response("Nenhum urlPdf encontrado para O.S. {$osNumero}", 404);

        $suffix   = ($emitir === 'true' || $emitir === true) ? '_emitir' : '';
        $relPath  = "laudos/{$osNumero}{$suffix}.pdf";
        $absPath  = Storage::path($relPath);

        if (!Storage::exists($relPath)) {
            try {
                $remote = Http::withOptions(['verify' => false])->get($urlPdf);
                if (!$remote->successful()) {
                    return response("Falha ao baixar PDF remoto. Status: " . $remote->status(), 502);
                }
                Storage::makeDirectory('laudos');
                Storage::put($relPath, $remote->body());
            } catch (\Throwable $e) {
                return response("Erro ao baixar/salvar PDF: " . $e->getMessage(), 500);
            }
        }

        $mtime = @filemtime($absPath) ?: time();
        $mtimeHdr = gmdate('D, d M Y H:i:s', $mtime) . ' GMT';
        $etag = @md5_file($absPath) ?: sha1($relPath . $mtime);

        $ifNoneMatch  = $request->headers->get('If-None-Match');
        $ifModified   = $request->headers->get('If-Modified-Since');

        if ($ifNoneMatch === $etag || $ifModified === $mtimeHdr) {
            return response('', 304, [
                'ETag' => $etag,
                'Last-Modified' => $mtimeHdr,
                'Cache-Control' => 'public, max-age=86400',
            ]);
        }

        $filename = 'laudo-' . preg_replace('/[^0-9A-Za-z_\-\.]/', '_', $osNumero) . '.pdf';

        return Response::file($absPath, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            'Cache-Control'       => 'public, max-age=86400',
            'ETag'                => $etag,
            'Last-Modified'       => $mtimeHdr,
            'Accept-Ranges'       => 'bytes',
        ]);
    }


    public function pdfFeedback(Request $request)
    {
        $osNumero = $request->query('osNumero');
        $emitir   = $request->query('emitir', 'false');
        if (!$osNumero) abort(404);

        $pdfUrl = route('paciente.os.abrir', [
            'osNumero' => $osNumero,
            'emitir'   => $emitir,
        ]);

        return view('paciente.pdf-feedback', compact('pdfUrl', 'osNumero'));
    }


    public function salvarFeedback(Request $request)
    {
        $data = $request->validate([
            'feedbackId' => 'nullable|integer',
            'osNumero'   => 'required_without:feedbackId|string|max:100',
            'nota'       => 'nullable|integer|min:1|max:5', 
            'comentario' => 'nullable|string|max:2000',
        ]);

      
        if (!empty($data['feedbackId'])) {
            $fp = FeedbackPaciente::find($data['feedbackId']);
            if (!$fp) return response()->json(['success' => false, 'message' => 'Feedback não encontrado'], 404);

            if (array_key_exists('nota', $data) && $data['nota'] !== null) $fp->nota = $data['nota'];
            if (array_key_exists('comentario', $data)) $fp->comentario = $data['comentario'];
            $fp->save();

            return response()->json(['success' => true, 'id' => $fp->id]);
        }

        
        if (empty($data['osNumero'])) {
            return response()->json(['success' => false, 'message' => 'O.S. obrigatória para criar feedback'], 422);
        }

        $exists = FeedbackPaciente::where('os_numero', $data['osNumero'])->exists();
        if ($exists) {
            return response()->json(['success' => false, 'message' => 'O.S. já avaliada'], 409);
        }

        $fp = FeedbackPaciente::create([
            'os_numero'  => $data['osNumero'],
            'nota'       => $data['nota'] ?? null,
            'comentario' => $data['comentario'] ?? null,
            'ip'         => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
        ]);

        return response()->json(['success' => true, 'id' => $fp->id], 201);
    }

    // =========================
    // VIEW: PDF clean com/sem footer (flag jaAvaliado)
    // Rota: paciente.pdf-clean (GET /paciente/pdf-clean)
    // =========================
    public function pdfFeedbackClean(Request $request)
    {
        $osNumero = $request->query('osNumero');
        $emitir   = $request->query('emitir', 'false');

        if (!$osNumero) abort(404, 'Número de O.S. não informado.');

        $pdfUrl = route('paciente.os.abrir', [
            'osNumero' => $osNumero,
            'emitir'   => $emitir,
        ]);

        $jaAvaliado = FeedbackPaciente::where('os_numero', $osNumero)->exists();

        return view('paciente.pdf-clean', compact('osNumero', 'pdfUrl', 'jaAvaliado'));
    }

    public function logout()
    {
        // Remove dados da sessão do paciente
        session()->forget('user');
        session()->forget('soap_cookie');

        // Destroi toda a sessão (opcional)
        // session()->flush();

        // Redireciona para login
        return redirect()->route('login')->with('message', 'Você saiu da sua conta.');
    }

    public function formAlterarSenha()
    {
        return view('paciente.alterar-senha');
    }


    public function AlterarSenha(Request $request)
    {
        // 1) Validação básica (vai popular $data)
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password'     => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        // pega os dados validados
        $data = $validator->validated();
        // observação: 'new_password_confirmation' é exigido pelo rule 'confirmed'

        // --- identificar usuário na sessão ---
        $sessionUser = session('user');
        $remoteUserId = $sessionUser['userId'] ?? $sessionUser['usuarioId'] ?? null;

        if (!$remoteUserId) {
            return redirect()->back()->with('password_error', 'Impossível identificar usuário para alteração de senha.');
        }

        $oldPass = $data['current_password'];
        $newPass = $data['new_password'];

        // --- monta envelope SOAP (usando o XML que você forneceu) ---
        $soapEnvelope = <<<XML
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:shif="{$this->soapNs}">
  <soapenv:Header/>
  <soapenv:Body>
    <shif:WsAlterarSenha>
      <shif:pUserId>{$remoteUserId}</shif:pUserId>
      <shif:pSenha>{$oldPass}</shif:pSenha>
      <shif:pNovaSenha>{$newPass}</shif:pNovaSenha>
    </shif:WsAlterarSenha>
  </soapenv:Body>
</soapenv:Envelope>
XML;

        $curlHeaders = [
            'Content-Type: text/xml; charset=utf-8',
            'SOAPAction: ' . "{$this->soapNs}/s01.util.b2b.shift.consultas.Webserver.WsAlterarSenha", // ajuste se necessário
        ];

        if ($cookie = session('soap_cookie')) {
            $curlHeaders[] = 'Cookie: ' . $cookie;
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->soapUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $soapEnvelope,
            CURLOPT_HTTPHEADER => $curlHeaders,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => 60,
        ]);

        $resp = curl_exec($ch);
        $errNo = curl_errno($ch);
        $errMsg = curl_error($ch);
        $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errNo) {
            return redirect()->back()->with('password_error', 'Erro ao conectar com o serviço: ' . $errMsg);
        }

        if (!is_string($resp) || trim($resp) === '') {
            return redirect()->back()->with('password_error', "Resposta vazia do serviço SOAP (HTTP {$httpStatus}).");
        }

        // parse XML e checar Faults / resultado
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        if (!@$dom->loadXML($resp)) {
            return redirect()->back()->with('password_error', 'Resposta inválida do serviço de alteração de senha.');
        }
        $xpath = new \DOMXPath($dom);

        // verifica Fault
        $fault = $xpath->query('//faultstring | //Fault/faultstring | //Fault');
        if ($fault && $fault->length > 0) {
            $faultText = trim((string)$fault->item(0)->textContent);
            $msg = $faultText ?: 'Erro no serviço SOAP (Fault).';
            return redirect()->back()->with('password_error', "Serviço recusou alteração: {$msg}");
        }

        // tentar inferir sucesso a partir de nós comuns
        $possibleNodes = [
            "//*[contains(local-name(), 'WsAlterarSenhaResponse')]",
            "//*[contains(local-name(), 'WsAlterarSenhaResult')]",
            "//*[contains(local-name(), 'Resultado')]",
            "//*[contains(local-name(), 'sucesso')]",
            "//*[contains(local-name(), 'Sucesso')]",
        ];

        $soapOk = null;
        $soapMessage = null;
        foreach ($possibleNodes as $q) {
            $nodes = $xpath->query($q);
            if ($nodes && $nodes->length > 0) {
                $txt = trim((string)$nodes->item(0)->textContent);
                if ($txt === '') continue;
                $lower = mb_strtolower($txt);
                if (in_array($lower, ['true', 'ok', 'sucesso', '1'])) {
                    $soapOk = true;
                    break;
                }
                if (str_contains($lower, 'sucesso') || str_contains($lower, 'ok') || str_contains($lower, 'alterado')) {
                    $soapOk = true;
                    break;
                }
                $soapOk = false;
                $soapMessage = $txt;
                break;
            }
        }

        if ($soapOk === null) {
            $soapOk = ($httpStatus >= 200 && $httpStatus < 300);
            if (!$soapOk) $soapMessage = "HTTP status {$httpStatus}";
        }

        if (!$soapOk) {
            $msg = $soapMessage ?? 'Servidor retornou falha ao alterar senha.';
            return redirect()->back()->with('password_error', $msg);
        }

        // === alteração remota OK -> atualiza sessão com nova senha criptografada ===
        try {
            $novaSenhaCripto = Crypt::encryptString($newPass);
        } catch (\Throwable $e) {
            $novaSenhaCripto = $newPass; // fallback (não recomendado)
        }

        $sessionUser['senha'] = $novaSenhaCripto;
        session(['user' => $sessionUser]);

        // opcional: limpar cookie SOAP se precisar forçar novo login
        // session()->forget('soap_cookie');

        return redirect()->back()->with('password_success', 'Senha alterada com sucesso.');
    }
}
