<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8" />
  <title>Laudo - O.S. {{ $osNumero }}</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

  <style>
    html, body { height:100%; margin:0; background:#111; font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial; }
    .viewer { height:100vh; display:flex; flex-direction:column; }

    /* header escuro */
    #topBar {
      height:48px;
      background:#202124;
      color:#fff;
      display:flex;
      align-items:center;
      gap:12px;
      padding:6px 12px;
      box-shadow:0 2px 6px rgba(0,0,0,0.25);
      z-index:10000;
    }
    #topBar .title { font-weight:600; font-size:0.95rem; color:#e8eaed; }
    .top-controls { display:flex; align-items:center; gap:8px; margin-left:auto; }
    .top-controls button, .top-controls .btn {
      color:#e8eaed;
      border-color:transparent;
      background:transparent;
      padding:6px 8px;
      border-radius:6px;
    }
    .top-controls button:hover { background:rgba(255,255,255,0.04); color:#fff; }

    /* PDF container: altura definida dinamicamente via blade inline-style */
    #pdfContainer {
      width:100%;
      background:#fff;
      display:flex;
      justify-content:center;
      overflow:auto;
    }

    #pdfEmbed {
      width:100%;
      height:100%;
      border:0;
      transform-origin:top left;
      background:#fff;
      display:block;
    }

    /* footer (quando renderizado) */
    #feedbackBar {
      min-height:90px;
      display:flex;
      align-items:center;
      justify-content:center;
      flex-wrap:wrap;
      gap:10px;
      padding:8px 12px;
      box-shadow:0 -2px 8px rgba(0,0,0,0.25);
      background:rgba(255,255,255,0.98);
      position:fixed;
      bottom:0;
      left:0;
      right:0;
      z-index:9999;
    }

    .star-rating { display:flex; flex-direction:row-reverse; gap:8px; align-items:center; }
    .star-rating input { display:none; }
    .star-rating label { font-size:20px; cursor:pointer; color:#bbb; user-select:none; }
    .star-rating input:checked ~ label,
    .star-rating label:hover,
    .star-rating label:hover ~ label { color:gold; }

    #feedbackComment { display:none; width:100%; max-width:500px; }
    #feedbackComment textarea { width:100%; resize:none; font-size:0.9rem; border-radius:6px; }
    #msgFeedback { font-size:0.9rem; margin-left:8px; color:#2a7a2a; }
  </style>
</head>
<body>
  <div class="viewer">
    <!-- header -->
    <div id="topBar">
      <div class="title">Laudo — O.S. {{ $osNumero }}</div>
      <div class="text-muted small ms-2" style="color:#9aa0a6;">{{ date('d/m/Y') }}</div>

      <div class="top-controls">      
        <div style="width:1px;height:28px;background:rgba(255,255,255,0.08);margin:0 6px;"></div>
        <a id="btnDownload" class="btn" href="{{ $pdfUrl }}" download title="Baixar PDF"><i class="bi bi-download"></i></a>
        <button id="btnPrint" title="Imprimir"><i class="bi bi-printer"></i></button>
      </div>
    </div>

    {{-- calcula altura do container do PDF dependendo se existe footer --}}
    @php
      $pdfContainerHeight = (empty($jaAvaliado) || $jaAvaliado == false) ? 'calc(100vh - 48px - 90px)' : 'calc(100vh - 48px)';
    @endphp

    <!-- PDF -->
    <div id="pdfContainer" style="height: {{ $pdfContainerHeight }};">
      <embed id="pdfEmbed" src="{{ $pdfUrl }}#toolbar=0&navpanes=0&scrollbar=0" type="application/pdf" />
    </div>

    {{-- Footer só é incluso se NÃO houver avaliação --}}
    @if (empty($jaAvaliado) || $jaAvaliado == false)
    <div id="feedbackBar">
      <div class="me-2 text-muted">Avalie sua experiencia em nosso laboratório:</div>
      <div class="star-rating">
        @for($i=5;$i>=1;$i--)
          <input type="radio" id="star{{ $i }}" name="rating" value="{{ $i }}">
          <label for="star{{ $i }}">★</label>
        @endfor
      </div>

      <div id="feedbackComment">
        <textarea id="comentario" rows="2" placeholder="Deseja deixar um comentário? (opcional)" class="form-control"></textarea>
      </div>

      <button id="btnEnviarFeedback" class="btn btn-outline-primary btn-sm">Enviar</button>

      <div id="msgFeedback" class="ms-3"></div>
    </div>
    @endif

  </div>

  <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
  <script>
    (function(){
      // flag enviada do backend
      const jaAvaliado = @json($jaAvaliado ?? false);

      const embed = document.getElementById('pdfEmbed');
      const btnPrint = document.getElementById('btnPrint');
      const osNumero = @json($osNumero);
      const commentDiv = document.getElementById('feedbackComment');
      const comentario = document.getElementById('comentario');
      const msg = document.getElementById('msgFeedback');
      const btnEnviar = document.getElementById('btnEnviarFeedback');

      // print
      if (btnPrint) {
        btnPrint.onclick = () => {
          const w = window.open('{{ $pdfUrl }}', '_blank');
          if (w) setTimeout(() => w.print(), 800);
          else alert('Permita pop-ups para imprimir o laudo.');
        };
      }

      // se já avaliado: não adiciona listeners e garante que footer não exista
      if (jaAvaliado) {
        // nada mais — footer não está no DOM
        return;
      }

      // mostra o campo de comentário quando selecionar estrela
      document.querySelectorAll('input[name="rating"]').forEach(el => {
        el.addEventListener('change', () => {
          if (commentDiv) {
            commentDiv.style.display = 'block';
            comentario && comentario.focus();
          }
        });
      });

      // envia feedback (se o botão existir)
      if (btnEnviar) {
        btnEnviar.addEventListener('click', async () => {
          msg.textContent = '';
          const ratingEl = document.querySelector('input[name="rating"]:checked');
          const nota = ratingEl ? parseInt(ratingEl.value, 10) : null;
          if (!nota) {
            msg.style.color = '#b36b00';
            msg.textContent = 'Selecione uma nota.';
            return;
          }

          btnEnviar.disabled = true;
          msg.style.color = '#2a7a2a';
          msg.textContent = 'Enviando...';

          try {
            const resp = await axios.post('{{ route("paciente.salvarFeedback") }}', {
              osNumero: osNumero,
              nota: nota,
              comentario: comentario ? comentario.value || null : null
            });

            if (resp.data && resp.data.success) {
              msg.style.color = '#2a7a2a';
              msg.textContent = 'Obrigado pelo feedback!';
              if (comentario) comentario.value = '';
              if (commentDiv) commentDiv.style.display = 'none';
              // opcional: esconder o formulário após enviar
              document.querySelectorAll('input[name="rating"]').forEach(i => i.disabled = true);
              btnEnviar.disabled = true;
            } else {
              if (resp.status === 409 || (resp.data && resp.data.message && resp.data.message.includes('já avaliada'))) {
                msg.style.color = 'crimson';
                msg.textContent = 'O.S. já foi avaliada anteriormente.';
              } else {
                msg.style.color = 'crimson';
                msg.textContent = 'Erro ao enviar.';
                btnEnviar.disabled = false;
              }
            }
          } catch (err) {
            console.error(err);
            if (err.response && err.response.status === 409) {
              msg.style.color = 'crimson';
              msg.textContent = err.response.data.message || 'O.S. já foi avaliada.';
            } else {
              msg.style.color = 'crimson';
              msg.textContent = 'Erro ao enviar.';
            }
            btnEnviar.disabled = false;
          }
        });
      }
    })();
  </script>
</body>
</html>
