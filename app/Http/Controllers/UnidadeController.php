<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Exception;

class UnidadeController extends Controller
{
    public function index(Request $request)
    {
        $units = [];
        try {
            // === Substitua pela URL real do serviço SOAP ===
            $endpoint = 'https://portal.laboratorioplatano.com.br:443/shift/lis/platano/elis/s01.util.b2b.shift.consultas.Webserver.cls';
            $soapAction = ''; // ajustar se necessário
            // =================================================

            $xml = <<<XML
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:shif="http://www.shift.com.br">
   <soapenv:Header/>
   <soapenv:Body>
      <shif:WsGetTodosUnidades>
         <shif:pConfiguracaoWeb>?</shif:pConfiguracaoWeb>
      </shif:WsGetTodosUnidades>
   </soapenv:Body>
</soapenv:Envelope>
XML;

            // cURL
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $endpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: text/xml; charset=utf-8',
                'Content-Length: ' . strlen($xml),
                'SOAPAction: "' . $soapAction . '"'
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

            // Em dev, se precisar ignorar SSL (não recomendado em produção)
            // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            // curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

            $response = curl_exec($ch);
            $curlErr  = curl_error($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $info     = curl_getinfo($ch);
            curl_close($ch);

            \Log::debug('SOAP endpoint: '.$endpoint);
            \Log::debug('SOAP curl info: '.json_encode($info));
            \Log::debug('SOAP response (sample): '.substr($response ?? '', 0, 2000));

            if ($curlErr) {
                throw new Exception("cURL error: $curlErr");
            }

            if ($httpCode < 200 || $httpCode >= 300) {
                throw new Exception("Servidor SOAP retornou HTTP $httpCode");
            }

            // parse e normalização
            $units = $this->parseSoapWithDom($response);

            // ordenar por cidade, depois nome
            usort($units, function($a, $b) {
                $ca = strtolower($a['cidade'] ?? '');
                $cb = strtolower($b['cidade'] ?? '');
                if ($ca === $cb) {
                    return strcmp(strtolower($a['nome'] ?? ''), strtolower($b['nome'] ?? ''));
                }
                return strcmp($ca, $cb);
            });

        } catch (Exception $e) {
            \Log::error('Erro SOAP unidades: '.$e->getMessage());
            session()->flash('error', 'Não foi possível carregar unidades: '.$e->getMessage());
        }

        return view('unidades.index', ['units' => $units]);
    }

    /**
     * Parse do XML SOAP usando DOM + XPath e normaliza campos.
     * Trata tags vazias (ex: <funcionamento/>) como ausentes.
     */
    private function parseSoapWithDom(string $responseXml): array
    {
        // Detecta respostas HTML (login/erro) e aborta
        if (stripos($responseXml, '<html') !== false && stripos($responseXml, '<listaUnidade') === false) {
            \Log::error('Resposta SOAP contem HTML (possivel login). Excerpt: '.substr($responseXml,0,1000));
            return [];
        }

        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        // remove caracteres inválidos
        $responseXml = preg_replace('/[\x00-\x1F\x7F]/u', '', $responseXml);

        if (!@$dom->loadXML($responseXml)) {
            if (preg_match('/<\s*soap[^>]*:Envelope.*<\/\s*soap[^>]*:Envelope\s*>/si', $responseXml, $m)
                || preg_match('/<\s*SOAP-ENV:Envelope.*<\/\s*SOAP-ENV:Envelope\s*>/si', $responseXml, $m)
                || preg_match('/<\s*soapenv:Envelope.*<\/\s*soapenv:Envelope\s*>/si', $responseXml, $m)) {
                $clean = $m[0];
                if (!@$dom->loadXML($clean)) {
                    \Log::error('Falha ao carregar XML do SOAP apos tentativa de limpeza.');
                    return [];
                }
            } else {
                \Log::error('Resposta SOAP nao contem Envelope XML valido.');
                return [];
            }
        }

        $xpath = new \DOMXPath($dom);
        $lists = $xpath->query("//*[local-name() = 'listaUnidade' or local-name() = 'listaunidade']");

        $units = [];

        // helper: busca field por vários nomes (case-insensitive). Retorna null se o valor final for vazio.
        $getByNames = function(\DOMElement $parent, array $names) {
            $doc = $parent->ownerDocument;
            $xp = new \DOMXPath($doc);
            foreach ($names as $n) {
                $q = ".//*[translate(local-name(), 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz') = '".strtolower($n)."']";
                $res = $xp->query($q, $parent);
                if ($res->length > 0) {
                    $vals = [];
                    foreach ($res as $r) {
                        $text = trim($r->textContent);
                        if ($text !== '') $vals[] = $text;
                    }
                    if (count($vals) === 0) {
                        // todas ocorrências existiam, mas eram vazias -> tratar como ausente
                        return null;
                    }
                    return implode("\n", $vals);
                }
            }
            return null;
        };

        // percorre listas
        if ($lists->length > 0) {
            foreach ($lists as $list) {
                $nodes = (new \DOMXPath($list->ownerDocument))->query(".//*[local-name() = 'unidade']", $list);
                foreach ($nodes as $node) {
                    // coleta child elements e só guarda se tiver texto não vazio
                    $raw = [];
                    foreach ($node->childNodes as $c) {
                        if ($c->nodeType !== XML_ELEMENT_NODE) continue;
                        $key = $c->localName;
                        $value = trim($c->textContent);
                        if ($value !== '') {
                            $raw[$key] = $value;
                        }
                    }

                    // normalizações: vários nomes possíveis
                    $nome = $raw['nome'] ?? $raw['Nome'] ?? ($raw['descricao'] ?? '');
                    $cidade = $raw['cidade'] ?? $raw['Cidade'] ?? '';
                    $bairro = $raw['bairro'] ?? $raw['Bairro'] ?? '';
                    $logradouro = $raw['logradouro'] ?? $raw['Logradouro'] ?? '';
                    $cep = $raw['cep'] ?? $raw['CEP'] ?? '';
                    $id = $raw['id'] ?? $raw['ID'] ?? null;

                    // telefone (busca por vários nomes). getByNames retorna null se tag vazia.
                    $telefone = $getByNames($node, ['telefone','Telefone','contato','Contato','telefoneContato','telefones']);
                    if ($telefone === null && isset($raw['telefone'])) $telefone = $raw['telefone'];

                    // funcionamento / horario (trata tag vazia como ausente)
                    $func = $getByNames($node, ['funcionamento','Funcionamento','horario','horarioAtendimento','horario_atendimento','Horario','HorarioAtendimento']);
                    if ($func === null && isset($raw['funcionamento'])) $func = $raw['funcionamento'];
                    // se ainda for '', normaliza para empty string
                    $func = is_string($func) ? trim($func) : '';

                    // gmap/coords
                    $gmap = $getByNames($node, ['gmapAddress','gmapaddress','gmap','geo','enderecoMap']);
                    if ($gmap === null && isset($raw['gmapAddress'])) $gmap = $raw['gmapAddress'];
                    $gmap = is_string($gmap) ? trim($gmap) : '';

                    // imagem
                    $imagem = $raw['imagem'] ?? $raw['Imagem'] ?? null;

                    $units[] = [
                        'id' => $id,
                        'nome' => $nome,
                        'estado' => $raw['estado'] ?? $raw['Estado'] ?? '',
                        'cidade' => $cidade,
                        'bairro' => $bairro,
                        'logradouro' => $logradouro,
                        'cep' => $cep,
                        'telefone' => $telefone ?? '',
                        'funcionamento' => $func,
                        'gmapAddress' => $gmap,
                        'imagem' => $imagem,
                        'raw' => $raw,
                    ];
                }
            }
        } else {
            // fallback: buscar qualquer <unidade>
            $nodes = $xpath->query("//*[local-name() = 'unidade']");
            foreach ($nodes as $node) {
                $raw = [];
                foreach ($node->childNodes as $c) {
                    if ($c->nodeType !== XML_ELEMENT_NODE) continue;
                    $k = $c->localName;
                    $v = trim($c->textContent);
                    if ($v !== '') $raw[$k] = $v;
                }

                $nome = $raw['nome'] ?? $raw['Nome'] ?? ($raw['descricao'] ?? '');
                $cidade = $raw['cidade'] ?? $raw['Cidade'] ?? '';
                $bairro = $raw['bairro'] ?? $raw['Bairro'] ?? '';
                $logradouro = $raw['logradouro'] ?? '';
                $cep = $raw['cep'] ?? '';
                $id = $raw['id'] ?? null;

                $telefone = $getByNames($node, ['telefone','Telefone','contato','Contato','telefoneContato','telefones']) ?: ($raw['telefone'] ?? '');
                $func = $getByNames($node, ['funcionamento','Funcionamento','horario','horarioAtendimento','horario_atendimento','Horario','HorarioAtendimento']) ?: ($raw['funcionamento'] ?? '');
                $func = is_string($func) ? trim($func) : '';
                $gmap = $getByNames($node, ['gmapAddress','gmapaddress','gmap','geo','enderecoMap']) ?: ($raw['gmapAddress'] ?? '');

                $imagem = $raw['imagem'] ?? null;

                $units[] = [
                    'id' => $id,
                    'nome' => $nome,
                    'estado' => $raw['estado'] ?? '',
                    'cidade' => $cidade,
                    'bairro' => $bairro,
                    'logradouro' => $logradouro,
                    'cep' => $cep,
                    'telefone' => $telefone ?? '',
                    'funcionamento' => $func,
                    'gmapAddress' => $gmap ?? '',
                    'imagem' => $imagem,
                    'raw' => $raw,
                ];
            }
        }

        return $units;
    }
}
