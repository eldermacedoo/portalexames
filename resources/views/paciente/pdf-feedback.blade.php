@extends('layouts.app')

@section('title', "O.S. $osNumero - Resultado")

@section('content')
<div class="container-fluid p-0" style="height:100vh; display:flex; flex-direction:column;">
    {{-- Área do PDF ocupa todo o espaço disponível acima do rodapé --}}
    @php
      // Altura do rodapé ~56px (py-2 + texto + margem), ajuste fino se mudar o conteúdo
      $footerH = '56px';
    @endphp

    <iframe
        id="pdfFrame"
        src="{{ $pdfUrl }}"
        title="Laudo - O.S. {{ $osNumero }}"
        style="width:100%; height:calc(100vh - {{ $footerH }}); border:none; flex:0 0 auto;"
    ></iframe>

    {{-- Rodapé fixo e discreto --}}
    <div id="feedbackBar"
        class="text-center py-2 bg-light border-top shadow-sm"
        style="position:fixed; left:0; right:0; bottom:0; width:100%; font-size:0.9rem; z-index:10;">
        <form id="feedbackForm" class="d-inline-flex align-items-center gap-2" onsubmit="return false;" aria-label="Formulário de feedback">
            <input type="hidden" id="osNumeroField" value="{{ $osNumero }}">
            <span class="me-1 text-muted">Avalie seu atendimento:</span>

            <div class="star-rating d-inline-flex" role="radiogroup" aria-label="Avaliação por estrelas">
                @for($i = 5; $i >= 1; $i--)
                    <input type="radio" id="star{{ $i }}" name="rating" value="{{ $i }}">
                    <label for="star{{ $i }}" title="{{ $i }} estrela{{ $i>1 ? 's' : '' }}" aria-label="{{ $i }} estrela{{ $i>1 ? 's' : '' }}">★</label>
                @endfor
            </div>

            <button type="button" id="btnEnviarFeedback" class="btn btn-sm btn-outline-primary">
                Enviar
            </button>
        </form>
        <div id="msgFeedback" class="mt-1 small" role="status" aria-live="polite"></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const btn = document.getElementById('btnEnviarFeedback');
    const msg = document.getElementById('msgFeedback');
    const form = document.getElementById('feedbackForm');
    const osNumero = document.getElementById('osNumeroField').value;

    function setMsg(type, text) {
        const map = { ok: 'text-success', warn: 'text-warning', err: 'text-danger' };
        msg.className = 'mt-1 small ' + (map[type] || '');
        msg.textContent = text || '';
    }

    btn.addEventListener('click', async () => {
        setMsg('', '');
        const ratingEl = document.querySelector('input[name="rating"]:checked');
        const nota = ratingEl ? parseInt(ratingEl.value, 10) : null;

        if (!nota) {
            setMsg('warn', 'Selecione uma nota.');
            return;
        }

        // trava enquanto envia
        btn.disabled = true;

        try {
            const resp = await fetch(@json(route('paciente.salvarFeedback')), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': @json(csrf_token())
                },
                body: JSON.stringify({ osNumero, nota })
            });

            const data = await resp.json().catch(() => ({}));

            if (resp.ok && data && data.success) {
                setMsg('ok', '✔ Obrigado pelo feedback!');
                form.querySelectorAll('input[name="rating"]').forEach(i => i.disabled = true);
                btn.disabled = true;
                return;
            }

            if (resp.status === 409 || (data && (data.message || '').toLowerCase().includes('já avaliad'))) {
                setMsg('err', data.message || 'O.S. já foi avaliada anteriormente.');
                btn.disabled = true; // mantém travado para evitar repetição
                form.querySelectorAll('input[name="rating"]').forEach(i => i.disabled = true);
                return;
            }

            setMsg('err', (data && data.message) ? data.message : 'Erro ao enviar. Tente novamente.');
            btn.disabled = false;

        } catch (e) {
            setMsg('err', 'Erro ao enviar. Tente novamente.');
            btn.disabled = false;
        }
    });
});
</script>

<style>
/* estrelas acessíveis e leves */
.star-rating { flex-direction: row-reverse; gap: 6px; }
.star-rating input { display: none; }
.star-rating label {
    font-size: 1.3rem;
    cursor: pointer;
    color: #bbb;
    transition: color .15s ease-in-out;
    user-select: none;
    line-height: 1;
}
.star-rating input:checked ~ label,
.star-rating label:hover,
.star-rating label:hover ~ label { color: gold; }
</style>
@endsection
