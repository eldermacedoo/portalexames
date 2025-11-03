@extends('layouts.app')

@section('title', "O.S. $osNumero - Resultado")

@section('content')
<div class="container-fluid p-0" style="height:100vh; display:flex; flex-direction:column;">

    {{-- PDF ocupa todo o espaço disponível --}}
    <iframe src="{{ $pdfUrl }}"
        id="pdfFrame"
        style="flex:1; width:100%; border:none;"
        title="Laudo - O.S. {{ $osNumero }}"></iframe>

    {{-- Rodapé fixo e discreto --}}
    <div id="feedbackBar"
        class="text-center py-2 bg-light border-top shadow-sm"
        style="position:relative; bottom:0; width:100%; font-size:0.9rem;">
        <form id="feedbackForm" class="d-inline-flex align-items-center" onsubmit="return false;">
            <input type="hidden" id="osNumeroField" value="{{ $osNumero }}">

            <span class="me-2 text-muted">Avalie seu atendimento:</span>

            <div class="star-rating d-inline-flex me-2">
                @for($i = 5; $i >= 1; $i--)
                    <input type="radio" id="star{{ $i }}" name="rating" value="{{ $i }}">
                    <label for="star{{ $i }}" title="{{ $i }} estrela{{ $i>1 ? 's' : '' }}">⭐</label>
                @endfor
            </div>

            <button type="button" id="btnEnviarFeedback" class="btn btn-sm btn-outline-primary">
                Enviar
            </button>
        </form>
        <div id="msgFeedback" class="mt-1 small"></div>
    </div>
</div>

{{-- Axios para envio --}}
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const btn = document.getElementById('btnEnviarFeedback');
    const msg = document.getElementById('msgFeedback');

    btn.addEventListener('click', async () => {
        msg.innerHTML = '';
        const osNumero = document.getElementById('osNumeroField').value;
        const ratingEl = document.querySelector('input[name="rating"]:checked');
        const nota = ratingEl ? parseInt(ratingEl.value, 10) : null;

        if (!nota) {
            msg.innerHTML = '<div class="text-warning">Selecione uma nota.</div>';
            return;
        }

        try {
            const resp = await axios.post('{{ route("pacientes.salvarFeedback") }}', {
                osNumero: osNumero,
                nota: nota
            });

            if (resp.data && resp.data.success) {
                msg.innerHTML = '<div class="text-success">✔ Obrigado pelo feedback!</div>';
                btn.disabled = true;
                document.querySelectorAll('.star-rating input').forEach(i => i.disabled = true);
            } else {
                msg.innerHTML = '<div class="text-danger">Erro ao enviar. Tente novamente.</div>';
            }
        } catch (err) {
            console.error(err);
            msg.innerHTML = '<div class="text-danger">Erro ao enviar. Tente novamente.</div>';
        }
    });
});
</script>

<style>
.star-rating {
    flex-direction: row-reverse;
}
.star-rating input {
    display: none;
}
.star-rating label {
    font-size: 1.3rem;
    cursor: pointer;
    color: #ccc;
    transition: color 0.2s;
}
.star-rating input:checked ~ label,
.star-rating label:hover,
.star-rating label:hover ~ label {
    color: gold;
}
</style>
@endsection
