<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class PacienteController extends Controller
{
    public function soapLogin(Request $request)
    {
        $user = $request->input('user');
        $senha = $request->input('senha');

        if (empty($user) || empty($senha)) {
            return response()->json(['ok' => false, 'message' => 'Usuário e senha obrigatórios.'], 400);
        }

        $url = 'https://portal.laboratorioplatano.com.br:443/shift/lis/platano/elis/s01.util.b2b.shift.consultas.Webserver.cls';
        $soapEnvelope = <<<XML
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:shif="http://www.shift.com.br">
  <soapenv:Header/>
  <soapenv:Body>
    <shif:WsLoginUsuario>
      <shif:pUsuario>{$user}</shif:pUsuario>
      <shif:pSenha>{$senha}</shif:pSenha>
    </shif:WsLoginUsuario>
  </soapenv:Body>
</soapenv:Envelope>
XML;

        $headers = [
            'SOAPAction: http://www.shift.com.br/s01.util.b2b.shift.consultas.Webserver.WsLoginUsuario',
            'Content-Type: text/xml; charset=utf-8'
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $soapEnvelope,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_HEADER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => 60,
        ]);

        $resp = curl_exec($ch);
        if ($resp === false) {
            $err = curl_error($ch);
            curl_close($ch);
            return response()->json(['ok' => false, 'message' => 'cURL error: ' . $err], 500);
        }

        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $header = substr($resp, 0, $headerSize);
        $body = substr($resp, $headerSize);
        curl_close($ch);

        // extrai Set-Cookie
        preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $header, $matches);
        $cookies = $matches[1] ?? [];

        if (count($cookies) > 0) {
            $cookieString = implode('; ', $cookies);
            session(['soap_cookie' => $cookieString]);
            return response()->json(['ok' => true, 'cookie' => $cookieString, 'body' => $body]);
        }

        return response()->json(['ok' => false, 'message' => 'Nenhum cookie retornado', 'body' => $body], 500);
    }

    public function listaPorPeriodo(Request $request)
    {
        $inicio = $request->query('inicio', date('Y-m-01'));
        $fim = $request->query('fim', date('Y-m-d'));

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
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:shif="http://www.shift.com.br">
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

        $url = 'https://portal.laboratorioplatano.com.br:443/shift/lis/platano/elis/s01.util.b2b.shift.consultas.Webserver.cls';
        $soapCookie = session('soap_cookie') ?? '';

        // headers variants to try
        $headersVariants = [
            ['SOAPAction' => 'http://www.shift.com.br/s01.util.b2b.shift.consultas.Webserver.WsGetListaExPacienteByPeriodo', 'Content-Type' => 'Request-Response'],
            ['SOAPAction' => 'http://www.shift.com.br/s01.util.b2b.shift.consultas.Webserver.WsGetListaExPacienteByPeriodo', 'Content-Type' => 'text/xml; charset=utf-8', 'Accept' => 'text/xml'],
            ['SOAPAction' => 'http://www.shift.com.br/s01.util.b2b.shift.consultas.Webserver.WsLoginUsuario', 'Content-Type' => 'Request-Response'],
        ];

        $attempts = [];
        $final = [];

        foreach ($headersVariants as $variant) {
            $curlHeaders = [];
            foreach ($variant as $k => $v) {
                $curlHeaders[] = "{$k}: {$v}";
            }
            if (!empty($soapCookie)) $curlHeaders[] = 'Cookie: ' . $soapCookie;

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
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
            $err = curl_error($ch);
            $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $snippet = is_string($resp) ? mb_substr($resp, 0, 4000) : '';

            $attempt = [
                'headers_sent' => $variant,
                'cookie_sent' => !empty($soapCookie),
                'http_status' => $httpStatus,
                'curl_errno' => $errNo,
                'curl_error' => $err,
                'response_snippet' => $snippet,
                'found_count' => 0,
                'found_sample' => []
            ];

            if ($errNo) {
                $attempt['note'] = 'cURL error';
                $attempts[] = $attempt;
                continue;
            }

            // try DOM parse
            $found = [];
            if (is_string($resp) && trim($resp) !== '') {
                libxml_use_internal_errors(true);
                $dom = new \DOMDocument();
                $loaded = @$dom->loadXML($resp);

                if ($loaded) {
                    $xpath = new \DOMXPath($dom);
                    $osNodes = $xpath->query("//*[local-name() = 'os']");

                    foreach ($osNodes as $osNode) {
                        // Extrai o número da O.S.
                        $numNode = $xpath->query(".//*[local-name() = 'osNumero']", $osNode);
                        if (!$numNode || $numNode->length === 0) continue;

                        $osNumero = trim($numNode->item(0)->textContent);

                        // Extrai SOMENTE mnemonicos dentro de listaProcedimento/osProcedimento/mnemonico
                        $mnemonicos = [];
                        $mnQuery = ".//*[local-name() = 'listaProcedimento']/*[local-name() = 'osProcedimento']/*[local-name() = 'mnemonico']";
                        $mnNodes = $xpath->query($mnQuery, $osNode);

                        foreach ($mnNodes as $mn) {
                            $val = trim($mn->textContent);
                            if ($val !== '') $mnemonicos[] = $val;
                        }

                        // remove duplicados e reindexa
                        $mnemonicos = array_values(array_unique($mnemonicos));

                        // **use $found (correção)**
                        $found[] = [
                            'osNumero' => $osNumero,
                            'mnemonicos' => $mnemonicos
                        ];
                    }
                } else {
                    // fallback regex after removing namespaces
                    $noNs = preg_replace('/xmlns[^=]*="[^"]*"/i', '', $resp);
                    $noNs = preg_replace('/(<\/?)[a-z0-9]+:([a-z0-9\-_]+)/i', '$1$2', $noNs);
                    if (preg_match_all('/<os\b[^>]*>(.*?)<\/os>/is', $noNs, $blocks)) {
                        foreach ($blocks[1] as $b) {
                            if (preg_match('/<osNumero[^>]*>(.*?)<\/osNumero>/is', $b, $mnum)) {
                                $osNumero = trim(strip_tags($mnum[1]));
                                $mn = [];
                                if (preg_match_all('/<mnemonico[^>]*>(.*?)<\/mnemonico>/is', $b, $mm)) {
                                    foreach ($mm[1] as $v) {
                                        $val = trim(strip_tags($v));
                                        if ($val !== '') $mn[] = $val;
                                    }
                                }
                                if (empty($mn) && preg_match_all('/<nome[^>]*>(.*?)<\/nome>/is', $b, $nn)) {
                                    foreach ($nn[1] as $v) {
                                        $val = trim(strip_tags($v));
                                        if ($val !== '') $mn[] = $val;
                                    }
                                }
                                $found[] = ['osNumero' => $osNumero, 'mnemonicos' => array_values(array_unique($mn))];
                            }
                        }
                    }
                }
            }

            $attempt['found_count'] = count($found);
            $attempt['found_sample'] = array_slice($found, 0, 10);
            $attempts[] = $attempt;

            if (!empty($found)) {
                $final = $found;
                break; // stop at first successful variant
            }
        }

        if (empty($final)) {
            return response()->json(['success' => false, 'message' => 'Nenhuma O.S. encontrada', 'attempts' => $attempts], 200);
        }

        return response()->json(['success' => true, 'osNumeros' => $final, 'attempts_summary' => array_map(function ($a) {
            return [
                'headers_sent' => $a['headers_sent'],
                'http_status' => $a['http_status'],
                'found_count' => $a['found_count']
            ];
        }, $attempts)]);
    }
}
