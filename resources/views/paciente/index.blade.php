@extends('layouts.app')

@section('title', 'Pacientes - O.S.')

@section('content')
<div class="container py-4">
    <h3>Consultar O.S. por Período</h3>

    <div id="alertPlaceholder" class="mt-3"></div>

    <div class="card my-3">
        <div class="card-body">
            <form id="filtro" onsubmit="return false;" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label>Início</label>
                    <input type="date" id="inicio" class="form-control" value="{{ date('Y-m-01') }}">
                </div>
                <div class="col-md-3">
                    <label>Fim</label>
                    <input type="date" id="fim" class="form-control" value="{{ date('Y-m-d') }}">
                </div>
                <div class="col-md-3">
                    <button id="btnBuscar" class="btn btn-primary">Buscar</button>
                </div>
                <div class="col-md-3 text-end">
                    <div id="loading" style="display:none;">
                        <div class="spinner-border spinner-border-sm"></div>
                        <small class="ms-1">Carregando...</small>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div id="resultArea" class="card" style="display:none;">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-striped" id="osTable">
                    <thead>
                        <tr>
                            <th>Codigo O.S</th>
                            <th>Data</th>
                            <th>Status</th>
                            <th>Exames</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody"></tbody>
                </table>
            </div>

            <div id="attemptsArea" style="margin-top:1rem; display:none;">
                <h6>Tentativas / Debug</h6>
                <div id="attemptsList"></div>
            </div>
        </div>
    </div>
</div>

{{-- axios CDN --}}
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        if (csrfMeta) axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfMeta.getAttribute('content');
        axios.defaults.withCredentials = true;
        axios.defaults.headers.common['Accept'] = 'application/json';

        const btnBuscar = document.getElementById('btnBuscar');
        const inicio = document.getElementById('inicio');
        const fim = document.getElementById('fim');
        const loading = document.getElementById('loading');
        const alertPlaceholder = document.getElementById('alertPlaceholder');
        const resultArea = document.getElementById('resultArea');
        const tableBody = document.getElementById('tableBody');
        const attemptsArea = document.getElementById('attemptsArea');
        const attemptsList = document.getElementById('attemptsList');
        const osTable = document.getElementById('osTable');

        function showAlert(msg, type = 'info') {
            alertPlaceholder.innerHTML =
                '<div class="alert alert-' + type + ' alert-dismissible">' +
                msg +
                '<button class="btn-close" data-bs-dismiss="alert"></button></div>';
        }

        function clearResults() {
            tableBody.innerHTML = '';
            attemptsList.innerHTML = '';
            attemptsArea.style.display = 'none';
            resultArea.style.display = 'none';
        }

        // formata 'YYYY-MM-DD' para 'DD/MM/YYYY'
        function formatDateYmdToDmy(ymd) {
            if (!ymd) return '-';
            const parts = String(ymd).split('-');
            if (parts.length === 3) {
                return `${parts[2].padStart(2, '0')}/${parts[1].padStart(2, '0')}/${parts[0]}`;
            }
            const d = new Date(ymd);
            if (!isNaN(d)) {
                return (
                    d.getDate().toString().padStart(2, '0') +
                    '/' +
                    (d.getMonth() + 1).toString().padStart(2, '0') +
                    '/' +
                    d.getFullYear()
                );
            }
            return ymd;
        }

        // mapeamento de status numérico -> texto
        function statusTextFromCode(codeOrText) {
            // se não vier nada → "Aguardando amostra"
            if (codeOrText === null || codeOrText === undefined || String(codeOrText).trim() === '') {
                return 'Aguardando amostra';
            }

            const s = String(codeOrText).trim();
            const num = parseInt(s, 10);
            if (!isNaN(num)) {
                switch (num) {
                    case 1:
                        return 'Disponível';
                    case 2:
                        return 'Disponível parcial';
                    case 3:
                        return 'Em processamento';
                    case 4:
                        return 'Consultado WEB';
                    case 5:
                        return 'Entregue';
                    case 6:
                        return 'Impresso';
                    case 7:
                        return 'Pronto / Não disponível online';
                    case 8:
                        return 'Não aplicado';
                    case 9:
                        return 'Não realizado';
                    case 10:
                        return 'Pendente de material';
                    case 11:
                        return 'Necessita recoleta';
                    default:
                        return s;
                }
            }

            const matchNum = s.match(/\b(10|11|[1-9])\b/);
            if (matchNum) return statusTextFromCode(parseInt(matchNum[0], 10));

            const low = s.toLowerCase();
            if (low.includes('disponível parcial') || low.includes('disponivel parcial')) return 'Disponível parcial';
            if (low.includes('dispon')) return 'Disponível';
            if (low.includes('process')) return 'Em processamento';
            if (low.includes('consult')) return 'Consultado WEB';
            if (low.includes('entreg')) return 'Entregue';
            if (low.includes('impr')) return 'Impresso';
            if (low.includes('pronto')) return 'Pronto / Não disponível online';
            if (low.includes('não aplicado') || low.includes('nao aplicado')) return 'Não aplicado';
            if (low.includes('não realizado') || low.includes('nao realizado')) return 'Não realizado';
            if (low.includes('pendente')) return 'Pendente de material';
            if (low.includes('recolet')) return 'Necessita recoleta';
            return s;
        }

        function statusBadge(rawStatus) {
            const text = statusTextFromCode(rawStatus);
            if (!text) return '<span class="status-badge status-default">—</span>';

            const low = text.toLowerCase();
            let cls = 'status-default';

            if (low.includes('disponível') && !low.includes('parcial')) cls = 'status-ok';
            else if (low.includes('parcial')) cls = 'status-partial';
            else if (low.includes('em processamento')) cls = 'status-processing';
            else if (low.includes('consultado')) cls = 'status-consultado';
            else if (low.includes('entreg')) cls = 'status-entregue';
            else if (low.includes('impresso')) cls = 'status-impresso';
            else if (low.includes('aguard')) cls = 'status-aguarde';
            else if (low.includes('pendente')) cls = 'status-pendente';
            else if (low.includes('recoleta')) cls = 'status-recoleta';
            else if (low.includes('não') || low.includes('nao')) cls = 'status-negado';

            // escapa caracteres especiais
            const esc = String(text)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');

            return `<span class="status-badge ${cls}">${esc}</span>`;
        }


        // função principal de busca das O.S.
        async function buscarOS(s, e) {
            if (!s || !e) {
                showAlert('Informe início e fim', 'warning');
                return;
            }

            clearResults();
            loading.style.display = 'inline-block';
            btnBuscar.disabled = true;

            try {
                const resp = await axios.get('/pacientes/periodo', {
                    params: {
                        inicio: s,
                        fim: e
                    }
                });
                const data = resp.data;
                console.log('listaPorPeriodo ->', data);

                if (data && data.success === true && Array.isArray(data.osNumeros)) {
                    if (data.osNumeros.length === 0) {
                        showAlert('Nenhuma O.S. encontrada.', 'warning');
                    } else {
                        const thead = osTable.querySelector('thead tr');
                        thead.innerHTML = `
                            <th>Codigo O.S</th>
                            <th>Data</th>
                            <th>Status</th>
                            <th>Exames</th>
                        `;

                        data.osNumeros.forEach(item => {
                            const tr = document.createElement('tr');
                            tr.style.cursor = 'pointer';
                            tr.title = 'Clique para abrir o PDF';

                            // número O.S.
                            const td1 = document.createElement('td');
                            td1.textContent = item.osNumero ?? '-';
                            tr.appendChild(td1);

                            // data
                            const tdData = document.createElement('td');
                            tdData.textContent = formatDateYmdToDmy(item.data ?? null);
                            tr.appendChild(tdData);

                            // status
                            const tdStatus = document.createElement('td');
                            tdStatus.innerHTML = statusBadge(item.status ?? null);
                            tr.appendChild(tdStatus);

                            // exames
                            const td2 = document.createElement('td');
                            if (Array.isArray(item.mnemonicos) && item.mnemonicos.length > 0) {
                                const maxShow = 20;
                                td2.textContent =
                                    item.mnemonicos.length <= maxShow ?
                                    item.mnemonicos.join(', ') :
                                    item.mnemonicos.slice(0, maxShow).join(', ') +
                                    ' ... (+' +
                                    (item.mnemonicos.length - maxShow) +
                                    ')';
                            } else td2.textContent = '-';
                            tr.appendChild(td2);

                            // clique → abre PDF conforme status
                            tr.addEventListener('click', () => {
                                const finalStatusText = statusTextFromCode(item.status ?? null) || '';
                                const blockedCodes = [3, 7, 8, 9, 10, 11];
                                let code = null;
                                const sraw = String(item.status ?? '').trim();
                                const pn = parseInt(sraw, 10);
                                if (!isNaN(pn)) code = pn;
                                else {
                                    const mm = sraw.match(/\b(10|11|[1-9])\b/);
                                    if (mm) code = parseInt(mm[0], 10);
                                }

                                // caso status seja "Aguardando amostra"
                                if (finalStatusText.toLowerCase().includes('aguardando amostra')) {
                                    showAlert('Exame ainda não coletado. Favor aguardar a coleta e processamento amostra para disponibilidade do laudo.', 'warning');
                                    return;
                                }

                                const isBlocked =
                                    (code !== null && blockedCodes.includes(code)) ||
                                    /em processamento|pronto|não aplicado|nao aplicado|não realizado|nao realizado|pendente de material|necessita recoleta/i.test(
                                        finalStatusText
                                    );

                                if (isBlocked) {
                                    showAlert(
                                        'O.S não disponível, favor verificar status da sua O.S ou entrar em contato com o laboratório.',
                                        'warning'
                                    );
                                    return;
                                }

                                // permitido → abre PDF
                                const url = `/paciente/pdf-clean?osNumero=${encodeURIComponent(item.osNumero)}&emitir=false`;
                                window.open(url, '_blank');
                            });

                            tableBody.appendChild(tr);
                        });

                        resultArea.style.display = 'block';
                        showAlert('Busca concluída. Clique em uma linha para abrir o PDF.', 'success');
                    }
                    return;
                }

                if (data && data.success === false && data.attempts) {
                    showAlert('Nenhuma O.S. encontrada — veja debug abaixo.', 'warning');
                    showAttempts(data.attempts, false);
                    return;
                }

                if (data && data.error) {
                    showAlert('Erro backend: ' + JSON.stringify(data), 'danger');
                } else {
                    showAlert('Resposta inesperada do servidor. Veja console.', 'danger');
                    console.log(data);
                }
            } catch (err) {
                console.error('Erro fetch:', err);
                let msg = 'Erro ao buscar O.S.';
                if (err.response) msg += ' Status ' + err.response.status;
                showAlert(msg, 'danger');
            } finally {
                loading.style.display = 'none';
                btnBuscar.disabled = false;
            }
        }

        function showAttempts(attempts, isSummary = false) {
            if (!Array.isArray(attempts) || attempts.length === 0) return;
            attemptsArea.style.display = 'block';
            attemptsList.innerHTML = '';

            attempts.forEach((a, idx) => {
                const div = document.createElement('div');
                div.className = 'mb-3';
                const header = document.createElement('div');
                header.innerHTML = `<strong>Tentativa ${idx + 1} — status: ${a.http_status ?? '-'} — found_count: ${a.found_count ?? '-'}</strong>`;
                div.appendChild(header);

                const pre = document.createElement('pre');
                pre.style.maxHeight = '200px';
                pre.style.overflow = 'auto';
                pre.textContent = isSummary ?
                    JSON.stringify(a, null, 2) :
                    a.response_snippet || '(sem snippet)';
                div.appendChild(pre);
                attemptsList.appendChild(div);
            });

            resultArea.style.display = 'block';
        }

        btnBuscar.addEventListener('click', function() {
            buscarOS(inicio.value, fim.value);
        });

        const hoje = new Date();
        const trintaDiasAtras = new Date();
        trintaDiasAtras.setDate(hoje.getDate() - 30);
        const inicioAuto = trintaDiasAtras.toISOString().slice(0, 10);
        const fimAuto = hoje.toISOString().slice(0, 10);
        inicio.value = inicioAuto;
        fim.value = fimAuto;
        buscarOS(inicioAuto, fimAuto);
    });
</script>



@endsection