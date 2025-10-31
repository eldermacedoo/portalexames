<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
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

                        // pega o <data> filho direto do <os>
                        $dateNodeList = $xpath->query("./*[local-name() = 'data']", $osNode);
                        $data = null;
                        if ($dateNodeList && $dateNodeList->length > 0) {
                            $dateStr = trim((string) $dateNodeList->item(0)->textContent);
                            // tenta validar como YYYY-MM-DD
                            $d = \DateTime::createFromFormat('Y-m-d', $dateStr);
                            if ($d && $d->format('Y-m-d') === $dateStr) {
                                $data = $dateStr;
                            } else {
                                // tenta parse mais genérico e normalizar
                                try {
                                    $d2 = new \DateTime($dateStr);
                                    $data = $d2->format('Y-m-d');
                                } catch (\Throwable $e) {
                                    // mantém null se não conseguir parsear
                                    $data = null;
                                }
                            }
                        }

                        // pega o <status> filho direto do <os> (novo)
                        $statusNodeList = $xpath->query("./*[local-name() = 'status']", $osNode);
                        $status = null;
                        if ($statusNodeList && $statusNodeList->length > 0) {
                            $s = trim((string) $statusNodeList->item(0)->textContent);
                            $status = ($s === '') ? null : $s;
                        }

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
                            $xmlFragment = '';
                            try {
                                $xmlFragment = $dom->saveXML($osNode);
                                if (is_string($xmlFragment)) $xmlFragment = mb_substr($xmlFragment, 0, 1000);
                            } catch (\Throwable $e) {
                                $xmlFragment = null;
                            }
                            $debugSnippet = $xmlFragment;
                        }

                        // monta o resultado (agora incluindo "data" e "status")
                        $found[] = [
                            'osNumero' => $osNumero,
                            'data' => $data, // YYYY-MM-DD ou null
                            'status' => $status, // <-- novo campo colocado logo após a data
                            'mnemonicos' => $mnemonicos,
                            'debug' => $debugSnippet
                        ];
                    }
                } else {
                    // opcional: salvar erro de parse em attempts para debugging
                    $attempt['note'] = 'XML load failed';
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


    public function abrirOsPdf(Request $request)
    {
        $osNumero = $request->query('osNumero');
        $emitir = $request->query('emitir', 'false');

        if (!$osNumero) {
            return response('Parâmetro osNumero é obrigatório', 400);
        }

        // checa sessão (mesma validação que você já usa)
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

        // monta envelope SOAP (igual ao detalha)
        $soapEnvelope = <<<XML
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:shif="http://www.shift.com.br">
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

        $url = 'https://portal.laboratorioplatano.com.br:443/shift/lis/platano/elis/s01.util.b2b.shift.consultas.Webserver.cls';
        $soapCookie = session('soap_cookie') ?? '';

        $curlHeaders = [
            'SOAPAction: http://www.shift.com.br/s01.util.b2b.shift.consultas.Webserver.WsGetListaExPaciente',
            'Content-Type: Request-Response'
        ];

        if (!empty($soapCookie)) $curlHeaders[] = 'Cookie: ' . $soapCookie;

        // executar o SOAP via cURL (mantendo configurações que já funcionam)
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $soapEnvelope,
            CURLOPT_HTTPHEADER => $curlHeaders,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        $resp = curl_exec($ch);
        $errNo = curl_errno($ch);
        $err = curl_error($ch);
        $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errNo) {
            return response("cURL error ao chamar SOAP: {$err}", 502);
        }
        if (!is_string($resp) || trim($resp) === '') {
            return response("Resposta vazia do serviço SOAP (status: {$httpStatus})", 502);
        }

        // parse XML e extrair urlPdf (primeiro)
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $loaded = @$dom->loadXML($resp);
        if (!$loaded) {
            // devolve um preview para debugging
            return response("Falha ao parsear XML SOAP. Preview: " . mb_substr($resp, 0, 2000), 502);
        }

        $xpath = new \DOMXPath($dom);
        $nodes = $xpath->query("//*[local-name() = 'ListaOS']/*[local-name() = 'os']/*[local-name() = 'urlPdf']");

        $urlPdf = null;
        if ($nodes && $nodes->length > 0) {
            $urlPdf = trim((string) $nodes->item(0)->textContent);
        } else {
            // fallback: qualquer <urlPdf> no documento
            $alt = $xpath->query("//*[local-name() = 'urlPdf']");
            if ($alt && $alt->length > 0) {
                $urlPdf = trim((string) $alt->item(0)->textContent);
            }
        }

        if (empty($urlPdf)) {
            return response("Nenhum urlPdf encontrado para O.S. {$osNumero}", 404);
        }

        // Agora: buscar o PDF remotamente e devolver com headers inline
        try {
            // usar Http client do Laravel (guzzle por baixo)
            // desativa verify para manter comportamento atual (igual cURL); em produção, corrija SSL.
            $remote = Http::withOptions(['verify' => false])->get($urlPdf);

            if (!$remote->successful()) {
                // caso de erro 403/404 etc do servidor remoto
                return response("Falha ao baixar PDF remoto. Status: " . $remote->status(), 502);
            }

            // tentar inferir tipo: se header remoto informar content-type, use-o; senão use application/pdf
            $contentType = $remote->header('Content-Type') ?: 'application/pdf';

            // desejar dar nome ao arquivo (podemos inferir do path da URL)
            $filename = 'exame-' . preg_replace('/[^0-9A-Za-z_\-\.]/', '_', $osNumero) . '.pdf';

            return response($remote->body(), 200)
                ->header('Content-Type', $contentType)
                ->header('Content-Disposition', 'inline; filename="' . $filename . '"')
                ->header('Cache-Control', 'private, max-age=3600');
        } catch (\Throwable $e) {
            return response("Erro ao buscar PDF remoto: " . $e->getMessage(), 500);
        }
    }
}
