<aside id="left-menu" class="d-none d-lg-block col-lg-3">
    @include('components.leftmenu_content')
</aside>

<script>
    // Keep leftmenu safe back behavior - no change in functionality, wrapped and defensive
    (function(){
        const btn = document.getElementById('leftmenu-safe-back');
        if (!btn) return;
        const welcomePaths = ['/', '/welcome'];

        function updateBtnState() {
            try {
                const stack = Array.isArray(window.__navStack) ? window.__navStack : [];
                if (!window.__isAuth || stack.length <= 1) {
                    btn.setAttribute('disabled', 'disabled');
                    btn.classList.add('disabled');
                    btn.title = 'No hay historial interno';
                } else {
                    btn.removeAttribute('disabled');
                    btn.classList.remove('disabled');
                    btn.title = '';
                }
            } catch (e) { try { btn.setAttribute('disabled','disabled'); } catch(_){} }
        }

        btn.addEventListener('click', function(e){
            e.preventDefault();
            try {
                const stack = Array.isArray(window.__navStack) ? window.__navStack : [];
                if (!window.__isAuth || stack.length <= 1) {
                    if (typeof window.modalNotification === 'function') window.modalNotification('No hay historial', 'No hay una página previa interna a la que regresar.', { template: 'info', delayAutoClose: 2500 });
                    return;
                }
                stack.pop();
                let target = null;
                while (stack.length) {
                    const cand = stack[stack.length - 1];
                    if (welcomePaths.includes(cand)) { stack.pop(); continue; }
                    target = cand; break;
                }
                window.__navStack = stack;
                try { sessionStorage.setItem('pg_nav_stack_v1', JSON.stringify(stack)); } catch(_){ }
                if (target) {
                    if (window.__pjaxEnabled && window.history && window.history.length > 1) { history.back(); setTimeout(updateBtnState,300); return; }
                    window.location.href = target;
                } else {
                    updateBtnState();
                    if (typeof window.modalNotification === 'function') window.modalNotification('No hay historial', 'No hay una página previa interna a la que regresar.', { template: 'info', delayAutoClose: 2500 });
                }
            } catch (err) {  }
        });

        updateBtnState();
        window.addEventListener('pg:navstack:changed', updateBtnState);
        let tries = 0; const iv = setInterval(()=>{ tries++; try{ updateBtnState(); }catch(_){} if(window.__pjaxEnabled || tries>40) clearInterval(iv); },100);
    })();
</script>