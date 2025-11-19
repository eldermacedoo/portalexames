{{-- resources/views/unidades/index.blade.php --}}
@extends('layouts.app')

@section('head')
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

    <style>
        .unit-card {
            border: 1px solid #e6e6e6;
            border-radius: 8px;
            padding: 18px;
            margin-bottom: 20px;
            background: #fff;
            box-shadow: 0 1px 2px rgba(0,0,0,0.04);
        }
        .unit-img {
            width: 120px;
            height: 90px;
            object-fit: cover;
            border-radius: 6px;
            border: 1px solid #ddd;
        }
        .placeholder-img {
            width: 120px;
            height: 90px;
            background: #f1f1f1;
            display:flex;
            align-items:center;
            justify-content:center;
            color:#bbb;
            border-radius:6px;
            border:1px solid #eee;
        }
        .unit-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2b5a3a;
            margin-bottom: 6px;
        }
        .unit-address small { color: #666; }
        .phone-number {
            font-weight: 600;
            color: #1a6fb3;
            margin-bottom: 8px;
        }
        .funcionamento {
            white-space: pre-wrap;
            color: #666;
            font-size: 0.95rem;
        }
        .map-link {
            display: inline-flex;
            align-items: center;
            gap:6px;
            font-size: 0.9rem;
        }
        .btn-whatsapp {
            background: #25d366;
            color: #fff !important;
            border-color: #25d366;
        }
        .btn-whatsapp:hover {
            background: #1ebe5d;
            color: #fff !important;
        }

        @media (max-width: 767px) {
            .unit-img, .placeholder-img {
                width: 90px;
                height: 70px;
            }
            .text-md-end { text-align: left !important; }
        }
    </style>
@endsection

@section('content')
<div class="container py-4">

    <h2 class="mb-4">Unidades de coleta</h2>

    {{-- FILTROS --}}
    <div class="row mb-3">
        <div class="col-md-4">
            <input id="filterNome" class="form-control" placeholder="Nome da unidade">
        </div>
        <div class="col-md-3">
            <input id="filterCidade" class="form-control" placeholder="Cidade">
        </div>
        <div class="col-md-3">
            <input id="filterBairro" class="form-control" placeholder="Bairro">
        </div>
        <div class="col-md-2 text-end">
            <button id="btnLimpar" class="btn btn-secondary">Limpar</button>
        </div>
    </div>

    {{-- ERRO --}}
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    {{-- NENHUMA --}}
    @if(empty($units) || count($units) === 0)
        <div class="alert alert-info">Nenhuma unidade encontrada.</div>
    @else

        @foreach($units as $u)

            @php
                // valores crus
                $phoneRaw = $u['telefone'] ?? '';
                $funcRaw  = $u['funcionamento'] ?? '';
                $gmapRaw  = $u['gmapAddress'] ?? '';
                $logradouro = $u['logradouro'] ?? '';
                $cidade = $u['cidade'] ?? '';
                $estado = $u['estado'] ?? '';

                // normalizados
                $phoneTrim = trim($phoneRaw);
                $funcTrim  = trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $funcRaw));
                $gmapTrim  = trim($gmapRaw);
                $logradouroTrim = trim($logradouro);
                $cidadeTrim = trim($cidade);
                $estadoTrim = trim($estado);

                // telefone numérico
                $phoneDigits = preg_replace('/\D+/', '', $phoneTrim);

                // coordenadas válidas?
                $hasCoords = false;
                if ($gmapTrim !== '' && strpos($gmapTrim, ',') !== false) {
                    $parts = explode(',', $gmapTrim);
                    if (trim($parts[0] ?? '') !== '' && trim($parts[1] ?? '') !== '') {
                        $hasCoords = true;
                    }
                }

                // endereço válido para rotas?
                $hasAddressForRoute = ($logradouroTrim !== '' && $cidadeTrim !== '' && $estadoTrim !== '');
                $enderecoCompleto = trim($logradouroTrim . ' ' . ($u['bairro'] ?? '') . ' ' . $cidadeTrim . ' ' . $estadoTrim);

                // decidir exibir coluna
                $mostrarColuna = (
                    $phoneTrim !== '' ||
                    $funcTrim !== '' ||
                    $hasCoords ||
                    $hasAddressForRoute ||
                    ($phoneDigits !== '' && strlen($phoneDigits) >= 10)
                );

                // URL localização
                $mapUrl = $hasCoords ? "https://www.google.com/maps?q=" . urlencode(trim($parts[0]) . ',' . trim($parts[1])) : null;

                // imagem
                $thumb = $u['imagem'] ?? asset('img/unidade-placeholder.png');
            @endphp

            <div class="unit-card row g-3 mb-3 unit-item"
                data-nome="{{ strtolower($u['nome'] ?? '') }}"
                data-cidade="{{ strtolower($u['cidade'] ?? '') }}"
                data-bairro="{{ strtolower($u['bairro'] ?? '') }}">

                {{-- COL 1 - IMAGEM --}}
                <div class="col-3 col-md-2 d-flex align-items-start justify-content-center">
                    <img src="{{ $thumb }}" class="unit-img" alt="foto {{ $u['nome'] }}">
                </div>

                {{-- COL 2 - TITULO + ENDEREÇO --}}
                <div class="{{ $mostrarColuna ? 'col-9 col-md-6' : 'col-9 col-md-10' }}">
                    <div class="unit-title">{{ $u['nome'] }}</div>

                    <div class="unit-address">
                        <div>
                            {{ $logradouroTrim }}
                            @if(!empty($u['bairro'])) — {{ $u['bairro'] }} @endif
                        </div>

                        @if($cidadeTrim !== '' || $estadoTrim !== '')
                            <small>{{ $cidadeTrim }} — {{ $estadoTrim }}</small><br>
                        @endif

                        @if(!empty($u['cep']))
                            <small>CEP: {{ $u['cep'] }}</small>
                        @endif
                    </div>
                </div>

                {{-- COL 3 - INFO EXTRAS (OPÇÃO B) --}}
                @if($mostrarColuna)
                    <div class="col-12 col-md-4 text-md-end">

                        {{-- TELEFONE --}}
                        @if($phoneTrim !== '')
                            <div class="phone-number mb-2">
                                <i class="fa fa-phone"></i> {{ $phoneTrim }}
                            </div>
                        @endif

                        {{-- FUNCIONAMENTO --}}
                        @if($funcTrim !== '')
                            <div class="funcionamento mb-2">
                                <strong>Horário de atendimento:</strong><br>
                                <small>{!! nl2br(e($funcTrim)) !!}</small>
                            </div>
                        @endif

                        {{-- LOCALIZAÇÃO --}}
                        @if($mapUrl)
                            <a class="map-link d-block mb-2" href="{{ $mapUrl }}" target="_blank">
                                <i class="fa fa-map-marker-alt" style="color:#cb3a3a"></i> Localização
                            </a>
                        @endif

                        {{-- VER ROTAS --}}
                        @if($hasAddressForRoute)
                            <a class="btn btn-sm btn-outline-primary d-block mb-2"
                               href="https://www.google.com/maps/dir/?api=1&destination={{ urlencode($enderecoCompleto) }}"
                               target="_blank">
                                Ver rotas
                            </a>
                        @endif

                        {{-- LIGAR AGORA (apenas mobile) --}}
                        @if($phoneDigits !== '')
                            <a class="btn btn-sm btn-outline-success d-block mb-2 d-md-none"
                               href="tel:{{ $phoneDigits }}">
                                <i class="fa fa-phone"></i> Ligar agora
                            </a>
                        @endif

                        {{-- WHATSAPP --}}
                        @if(strlen($phoneDigits) >= 10)
                            <a class="btn btn-sm btn-whatsapp d-block"
                               href="https://wa.me/55{{ $phoneDigits }}"
                               target="_blank">
                                <i class="fab fa-whatsapp"></i> WhatsApp da unidade
                            </a>
                        @endif

                    </div>
                @endif

            </div>
        @endforeach

    @endif
</div>
@endsection

@section('scripts')
<script>
    (function(){
        const items = [...document.querySelectorAll('.unit-item')];
        const nome = document.getElementById('filterNome');
        const cidade = document.getElementById('filterCidade');
        const bairro = document.getElementById('filterBairro');
        const btnLimpar = document.getElementById('btnLimpar');

        function applyFilter(){
            const fNome = nome.value.toLowerCase();
            const fCid  = cidade.value.toLowerCase();
            const fBai  = bairro.value.toLowerCase();

            items.forEach(it => {
                const okNome = it.dataset.nome.includes(fNome);
                const okCid  = it.dataset.cidade.includes(fCid);
                const okBai  = it.dataset.bairro.includes(fBai);

                it.style.display = (okNome && okCid && okBai) ? '' : 'none';
            });
        }

        nome.addEventListener('input', applyFilter);
        cidade.addEventListener('input', applyFilter);
        bairro.addEventListener('input', applyFilter);

        btnLimpar.addEventListener('click', ()=>{
            nome.value = '';
            cidade.value = '';
            bairro.value = '';
            applyFilter();
        });
    })();
</script>
@endsection
