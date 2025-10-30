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
                    <button id="btnBuscar" class="btn btn-primary">Buscar O.S.</button>                    
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
            <h5 class="card-title">O.S. Encontradas</h5>
            <div class="table-responsive">
                <table class="table table-sm table-striped" id="osTable">
                    <thead>
                        <tr><th>osNumero</th><th>mnemonicos</th></tr>
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
    // CSRF meta in layout <head> required for POST
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

    function showAlert(msg, type='info') {
        alertPlaceholder.innerHTML = '<div class="alert alert-'+type+' alert-dismissible">'+msg+'<button class="btn-close" data-bs-dismiss="alert"></button></div>';
    }

    function clearResults() {
        tableBody.innerHTML = '';
        attemptsList.innerHTML = '';
        attemptsArea.style.display = 'none';
        resultArea.style.display = 'none';        
    }

    btnBuscar.addEventListener('click', async () => {
        const s = inicio.value;
        const e = fim.value;
        if (!s || !e) { showAlert('Informe início e fim', 'warning'); return; }

        clearResults();
        loading.style.display = 'inline-block';
        btnBuscar.disabled = true;

        try {
            const resp = await axios.get('/pacientes/periodo', { params: { inicio: s, fim: e } });
            const data = resp.data;
            console.log('listaPorPeriodo ->', data);

            if (data && data.success === true && Array.isArray(data.osNumeros)) {
                if (data.osNumeros.length === 0) {
                    showAlert('Nenhuma O.S. encontrada (verifique attempts para debug).', 'warning');
                    // show attempts if any in attempts_summary or attempts returned (controller returns attempts only on failure)
                    if (data.attempts) showAttempts(data.attempts);
                } else {
                    data.osNumeros.forEach(item => {
                        const tr = document.createElement('tr');
                        const td1 = document.createElement('td');
                        td1.textContent = item.osNumero;
                        const td2 = document.createElement('td');
                        td2.textContent = (Array.isArray(item.mnemonicos) ? item.mnemonicos.join(', ') : '');
                        tr.appendChild(td1); tr.appendChild(td2);
                        tableBody.appendChild(tr);
                    });
                    resultArea.style.display = 'block';                   
                    showAlert('Busca concluída.', 'success');
                    if (data.attempts_summary) console.log('attempts_summary', data.attempts_summary);
                }
                return;
            }

            // if failure with attempts debug
            if (data && data.success === false && data.attempts) {
                showAlert('Nenhuma O.S. encontrada — veja debug abaixo.', 'warning');
                showAttempts(data.attempts);
                return;
            }

            // fallback show raw content if exists
            if (data && data.raw_xml) {
                showAlert('Resposta inesperada — servidor retornou raw XML. Veja console.', 'warning');
                console.log('raw_xml:', data.raw_xml);
            } else if (data && data.error) {
                showAlert('Erro backend: ' + JSON.stringify(data), 'danger');
                console.log(data);
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
    });

    function showAttempts(attempts) {
        attemptsArea.style.display = 'block';
        attemptsList.innerHTML = '';
        attempts.forEach((a, idx) => {
            const div = document.createElement('div');
            div.className = 'mb-3';
            const header = document.createElement('div');
            header.innerHTML = `<strong>Tentativa ${idx+1} — status: ${a.http_status} — found_count: ${a.found_count}</strong>`;
            const pre = document.createElement('pre');
            pre.style.maxHeight = '220px';
            pre.style.overflow = 'auto';
            pre.textContent = a.response_snippet || '(sem snippet)';
            div.appendChild(header);
            div.appendChild(pre);
            attemptsList.appendChild(div);
        });
        resultArea.style.display = 'block';
    }
});
</script>
@endsection
