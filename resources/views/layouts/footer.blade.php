<footer class="site-footer">
    © {{ date('Y') }} Portal Exames. Todos os direitos reservados.
</footer>
<script>
    (function() {
        const hasServerError = {
            !!json_encode(session() - > has('password_error')) !!
        };
        const hasServerSuccess = {
            !!json_encode(session() - > has('password_success')) !!
        };
        const hasValidation = {
            !!json_encode($errors - > any()) !!
        };

        if (hasServerError || hasServerSuccess || hasValidation) {
            document.addEventListener('DOMContentLoaded', function() {
                const modalEl = document.getElementById('alterarSenhaModal');
                if (!modalEl) return;
                try {
                    const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
                    bsModal.show();
                    // opcional: foco no primeiro input
                    const first = modalEl.querySelector('input[name="current_password"]');
                    if (first) setTimeout(() => first.focus(), 200);
                } catch (e) {
                    const trigger = document.querySelector('[data-bs-target="#alterarSenhaModal"]');
                    if (trigger) trigger.click();
                }
            });
        }
    })();
</script>


<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>