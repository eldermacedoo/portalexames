<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class PacienteController extends Controller
{
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

        $headersVariants = [
            ['SOAPAction' => 'http://www.shift.com.br/s01.util.b2b.shift.consultas.Webserver.WsGetListaExPacienteByPeriodo', 'Content-Type' => 'Request-Response']
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
                        // pega somente o osNumero filho direto do nó <os>
                        $numNodeList = $xpath->query("./*[local-name() = 'osNumero']", $osNode);
                        if (!$numNodeList || $numNodeList->length === 0) continue;
                        $osNumero = trim((string) $numNodeList->item(0)->textContent);

                        // 1) tentativa específica: listaProcedimento/osProcedimento/mnemonico
                        $mnQuerySpecific = "./*[local-name() = 'listaProcedimento']/*[local-name() = 'osProcedimento']/*[local-name() = 'mnemonico']";
                        $mnNodes = $xpath->query($mnQuerySpecific, $osNode);

                        $mnemonicos = [];

                        if ($mnNodes && $mnNodes->length > 0) {
                            foreach ($mnNodes as $mn) {
                                $val = trim((string) $mn->textContent);
                                if ($val !== '') $mnemonicos[] = $val;
                            }
                        } else {
                            // 2) tentativa mais ampla: qualquer <mnemonico> dentro do <os>
                            $mnNodesBroad = $xpath->query(".//*[local-name() = 'mnemonico']", $osNode);
                            if ($mnNodesBroad && $mnNodesBroad->length > 0) {
                                // filtro: aceitar apenas mnemonicos que tenham ancestor listaProcedimento ou osProcedimento
                                foreach ($mnNodesBroad as $mn) {
                                    // percorre ancestry para checar se está dentro de listaProcedimento/osProcedimento
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

                        // 3) fallback final: se ainda vazio, tentar pegar <nome> dentro de osProcedimento (somente como fallback opcional)
                        if (empty($mnemonicos)) {
                            $nameNodes = $xpath->query("./*[local-name() = 'listaProcedimento']/*[local-name() = 'osProcedimento']/*[local-name() = 'nome']", $osNode);
                            if ($nameNodes && $nameNodes->length > 0) {
                                foreach ($nameNodes as $n) {
                                    $v = trim((string) $n->textContent);
                                    if ($v !== '') $mnemonicos[] = $v;
                                }
                            }
                        }

                        // normaliza: únicos e reindexados
                        $mnemonicos = array_values(array_unique($mnemonicos));

                        // se ainda vazio, capture um snippet pequeno do <os> para depuração (não muito grande)
                        $debugSnippet = null;
                        if (empty($mnemonicos)) {
                            // obter XML do osNode limitado a 1000 chars para não explodir resposta
                            $xmlFragment = '';
                            try {
                                $xmlFragment = $dom->saveXML($osNode);
                                if (is_string($xmlFragment)) $xmlFragment = mb_substr($xmlFragment, 0, 1000);
                            } catch (\Throwable $e) {
                                $xmlFragment = null;
                            }
                            $debugSnippet = $xmlFragment;
                        }

                        // monta o resultado
                        $found[] = [
                            'osNumero' => $osNumero,
                            'mnemonicos' => $mnemonicos,
                            'debug' => $debugSnippet // null quando ok, string curta quando vazio (útil para inspeção)
                        ];
                    }
                } else {
                    echo "esta caindo no else";
                }
            }

            $attempt['found_count'] = count($found);
            $attempt['found_sample'] = array_slice($found, 0, 10);
            $attempts[] = $attempt;

            if (!empty($found)) {
                $final = $found;
                break;
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
