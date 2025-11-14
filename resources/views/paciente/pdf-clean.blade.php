<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8" />
  <title>Laudo - O.S. {{ $osNumero }}</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <style>
    :root{
      --topbar-h:48px; --footer-h:90px;
      --bg:#111; --top:#202124; --text:#e8eaed; --muted:#9aa0a6;
      --white:#fff; --ok:#2a7a2a; --warn:#b36b00; --err:crimson;
    }
    *{box-sizing:border-box}
    html,body{height:100%;margin:0;background:var(--bg);font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial}
    body.evaluated #pdfContainer{height:calc(100vh - var(--topbar-h))}
    body:not(.evaluated) #pdfContainer{height:calc(100vh - var(--topbar-h) - var(--footer-h))}
    .top{height:var(--topbar-h);background:var(--top);color:var(--text);display:flex;align-items:center;padding:6px 12px;gap:12px;box-shadow:0 2px 6px rgba(0,0,0,.25);z-index:10}
    .title{font-weight:600;font-size:.95rem}
    .muted{font-size:.82rem;color:var(--muted)}
    .controls{margin-left:auto;display:flex;align-items:center;gap:6px}
    .btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:6px 8px;border-radius:6px;border:0;background:transparent;color:var(--text);cursor:pointer;text-decoration:none}
    .btn:hover{background:rgba(255,255,255,.06)}
    .i{width:18px;height:18px;display:inline-block}
    #pdfContainer{width:100%;background:var(--white);display:flex;justify-content:center;overflow:auto}
    #pdfEmbed{width:100%;height:100%;border:0;display:block;background:#fff}
    #feedbackBar{min-height:var(--footer-h);display:flex;align-items:center;justify-content:center;gap:10px;padding:8px 12px;box-shadow:0 -2px 8px rgba(0,0,0,.25);background:rgba(255,255,255,.98);position:fixed;bottom:0;left:0;right:0;z-index:9;flex-wrap:wrap}
    .stars{display:flex;flex-direction:row-reverse;gap:8px}
    .stars input{display:none}
    .stars label{font-size:20px;cursor:pointer;color:#bbb;user-select:none}
    .stars input:checked ~ label,
    .stars label:hover,
    .stars label:hover ~ label{color:gold}
    #feedbackComment{display:none;width:100%;max-width:500px}
    textarea{width:100%;resize:none;font-size:.9rem;border-radius:6px;padding:8px;border:1px solid #ccc}
    #msgFeedback{font-size:.9rem;margin-left:8px;color:var(--ok)}
    .sep{width:1px;height:28px;background:rgba(255,255,255,.08);margin:0 6px}
    @media (max-width:540px){ .title{font-size:.9rem} .muted{display:none} }
  </style>
</head>
<body class="{{ empty($jaAvaliado) || $jaAvaliado == false ? '' : 'evaluated' }}">
  <div class="top" role="banner">
    <div class="title">Laudo — O.S. {{ $osNumero }}</div>
    <div class="muted">{{ date('d/m/Y') }}</div>
    <div class="controls" role="toolbar" aria-label="Controles">
      <div class="sep" aria-hidden="true"></div>
      <a id="btnDownload" class="btn" href="{{ $pdfUrl }}" download title="Baixar PDF" aria-label="Baixar PDF">
        <svg class="i" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M5 20h14v-2H5v2zm7-18-5 5h3v6h4V7h3l-5-5z"/></svg>
      </a>
      <button id="btnPrint" class="btn" title="Imprimir" aria-label="Imprimir">
        <svg class="i" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M19 8H5c-1.66 0-3 1.34-3 3v4h4v4h12v-4h4v-4c0-1.66-1.34-3-3-3zM17 19H7v-5h10v5zM17 3H7v4h10V3z"/></svg>
      </button>
    </div>
  </div>

  <div id="pdfContainer">
    <embed id="pdfEmbed" src="{{ $pdfUrl }}#toolbar=0&navpanes=0&scrollbar=0" type="application/pdf" />
  </div>

  @if (empty($jaAvaliado) || $jaAvaliado == false)
  <div id="feedbackBar" role="region" aria-label="Feedback">
    <div class="muted" style="color:#333">Avalie sua experiência em nosso laboratório:</div>

    <div class="stars" role="radiogroup" aria-label="Avaliação por estrelas">
      @for($i=5;$i>=1;$i--)
        <input type="radio" id="star{{ $i }}" name="rating" value="{{ $i }}">
        <label for="star{{ $i }}" aria-label="{{ $i }} estrela(s)">★</label>
      @endfor
    </div>

    <div id="feedbackComment">
      <textarea id="comentario" rows="2" placeholder="Deseja deixar um comentário? (opcional)"></textarea>
    </div>

    <button id="btnEnviarFeedback" class="btn" style="border:1px solid #0d6efd;color:#0d6efd">Enviar</button>
    <div id="msgFeedback" class="ms-3" aria-live="polite"></div>
  </div>
  @endif

  <script>
    (function(){
      const jaAvaliado = @json($jaAvaliado ?? false);
      const pdfUrl = @json($pdfUrl);
      const osNumero = @json($osNumero);
      const qs = s => document.querySelector(s);

      // Print: abre em nova aba e aciona print (tenta)
      const btnPrint = qs('#btnPrint');
      if (btnPrint) btnPrint.addEventListener('click', () => {
        const w = window.open(pdfUrl, '_blank', 'noopener,noreferrer');
        if (w) setTimeout(()=>{ try{ w.focus(); w.print(); }catch(e){} }, 400);
        else alert('Permita pop-ups para imprimir o laudo.');
      });

      if (jaAvaliado) return;

      // Mostrar textarea ao escolher nota (delegação)
      document.addEventListener('change', e => {
        if (!e.target.matches('input[name="rating"]')) return;
        const commentDiv = qs('#feedbackComment');
        if (commentDiv) { commentDiv.style.display = 'block'; qs('#comentario')?.focus(); }
      });

      // Envio do feedback (fetch nativo)
      const btnEnviar = qs('#btnEnviarFeedback');
      if (!btnEnviar) return;
      btnEnviar.addEventListener('click', async () => {
        const msg = qs('#msgFeedback');
        msg.textContent = '';
        const sel = document.querySelector('input[name="rating"]:checked');
        const nota = sel ? parseInt(sel.value,10) : null;
        if (!nota) { msg.style.color='var(--warn)'; msg.textContent='Selecione uma nota.'; return; }

        btnEnviar.disabled = true;
        msg.style.color='var(--ok)'; msg.textContent='Enviando...';

        try {
          const resp = await fetch(@json(route('paciente.salvarFeedback')), {
            method:'POST',
            headers:{
              'Content-Type':'application/json',
              'X-CSRF-TOKEN': @json(csrf_token())
            },
            body: JSON.stringify({
              osNumero, nota,
              comentario: qs('#comentario')?.value || null
            })
          });

          const data = await resp.json().catch(()=>({}));
          if (resp.ok && data && data.success) {
            msg.style.color='var(--ok)'; msg.textContent='Obrigado pelo feedback!';
            qs('#comentario') && (qs('#comentario').value = '');
            qs('#feedbackComment') && (qs('#feedbackComment').style.display='none');
            document.querySelectorAll('input[name="rating"]').forEach(i=>i.disabled=true);
            btnEnviar.disabled = true;
          } else if (resp.status===409 || (data && /já avaliada/i.test(data.message || ''))) {
            msg.style.color='var(--err)'; msg.textContent = data.message || 'O.S. já foi avaliada anteriormente.';
            btnEnviar.disabled = false;
          } else {
            msg.style.color='var(--err)'; msg.textContent = (data && data.message) ? data.message : 'Erro ao enviar.';
            btnEnviar.disabled = false;
          }
        } catch (err) {
          msg.style.color='var(--err)'; msg.textContent='Erro ao enviar.';
          btnEnviar.disabled = false;
        }
      });
    })();
  </script>
</body>
</html>
