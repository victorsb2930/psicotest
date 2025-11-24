<script>
window.__currentUserTz = @json(optional(auth()->user())->timezone ?? null);
</script>
<script>
;(function(){
    const container = document.getElementById('pg-next-appt');
    if (!container) return;
    // prevent double-initialization if the DOM script runs again
    if (container.dataset.joinScriptInit === '1') return;
    container.dataset.joinScriptInit = '1';
    const apptId = container.getAttribute('data-appt-id');
    const startIso = container.getAttribute('data-start');
    if (!apptId || !startIso) return;

    let joinBtn = container.querySelector('[data-appt-action="join"]');

    function disableBtn() {
        try { if (!joinBtn) joinBtn = container.querySelector('[data-appt-action="join"]'); if (!joinBtn) return; joinBtn.disabled = true; joinBtn.classList.add('disabled'); joinBtn.setAttribute('aria-disabled','true'); } catch(e){}
    }
    function enableBtn() {
        try { if (!joinBtn) joinBtn = container.querySelector('[data-appt-action="join"]'); if (!joinBtn) return; joinBtn.disabled = false; joinBtn.classList.remove('disabled'); joinBtn.removeAttribute('aria-disabled'); } catch(e){}
    }
    disableBtn();

    function getLocalDateParts(date, tz) {
        try {
            const opts = { timeZone: tz || undefined, year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', second: '2-digit' };
            const fmt = new Intl.DateTimeFormat(undefined, opts);
            const parts = fmt.formatToParts(date);
            const obj = {};
            for (const p of parts) {
                if (p.type && p.type !== 'literal') obj[p.type] = p.value;
            }
            return {
                year: parseInt(obj.year,10),
                month: parseInt(obj.month,10),
                day: parseInt(obj.day,10),
                hour: parseInt(obj.hour,10),
                minute: parseInt(obj.minute,10),
                second: parseInt(obj.second,10)
            };
        } catch (e) {
            return { year: date.getFullYear(), month: date.getMonth()+1, day: date.getDate(), hour: date.getHours(), minute: date.getMinutes(), second: date.getSeconds() };
        }
    }

    function isSameLocalDay(d1, d2) {
        const tz = (typeof window !== 'undefined' && window.__currentUserTz) ? window.__currentUserTz : undefined;
        const a = getLocalDateParts(d1, tz);
        const b = getLocalDateParts(d2, tz);
        return a.year === b.year && a.month === b.month && a.day === b.day;
    }

    async function fetchMeta() {
        try {
            const res = await fetch(`/appointments/${encodeURIComponent(apptId)}/meta`, { headers: { 'Accept':'application/json' } });
            if (!res.ok) return null;
            return await res.json();
        } catch (e) { return null; }
    }

    // Configuration: start polling this many minutes before appointment
    const startPollingBeforeMinutes = 30;

    // Polling loop (every 10s) to detect acceptance and 5-min window
    let pollHandle = null;

    // status node to show friendly messages
    let statusNode = container.querySelector('.appt-join-status');
    if (!statusNode) {
        statusNode = document.createElement('div');
        statusNode.className = 'appt-join-status small text-muted mb-1';
        try {
            if (joinBtn && joinBtn.parentNode) joinBtn.parentNode.insertBefore(statusNode, joinBtn);
            else {
                const firstCol = container.querySelector('.me-3') || container;
                firstCol.appendChild(statusNode);
            }
        } catch (e) { try { container.appendChild(statusNode); } catch(_){} }

        // Observe DOM mutations so if the join button is re-rendered we disable it immediately
        try {
            const mo = new MutationObserver(function(){
                try {
                    const jb = container.querySelector('[data-appt-action="join"]');
                    if (jb) {
                        joinBtn = jb;
                        disableBtn();
                        try { if (statusNode && statusNode.parentNode) {} else { if (joinBtn && joinBtn.parentNode) joinBtn.parentNode.insertBefore(statusNode, joinBtn); } } catch(_){ }
                    }
                } catch(_){ }
            });
            mo.observe(container, { childList: true, subtree: true });
        } catch (e) {}

        // When returning to the tab, ensure button is disabled until poll allows it
        try {
            document.addEventListener('visibilitychange', function(){
                if (document.visibilityState === 'visible') {
                    try { disableBtn(); } catch(_){ }
                    try { if (!pollHandle) schedulePollingOnDay(); } catch(_){ }
                }
            });
        } catch(e) {}
    }

    function setStatus(text) { try { statusNode.textContent = text; } catch (e) {} }

    function startCountdown(targetTs) {
        let countdownInterval = null;
        if (countdownInterval) clearInterval(countdownInterval);
        countdownInterval = setInterval(function(){
            const now = Date.now();
            const diff = Math.max(0, targetTs - now);
            const mins = Math.floor(diff / 60000);
            const secs = Math.floor((diff % 60000) / 1000);
            setStatus(`Esperando aceptación — habilitará en ${mins}m ${secs}s`);
            if (diff <= 0) { clearInterval(countdownInterval); countdownInterval = null; }
        }, 1000);
    }

    async function startPolling() {
        if (pollHandle) return;
        const doPoll = async () => {
            const meta = await fetchMeta();
            if (!joinBtn) joinBtn = container.querySelector('[data-appt-action="join"]');
            // Default to disabled state on each poll to avoid stale enabled state
            disableBtn();
            if (!meta || !meta.ok) return;
            const status = (meta.status || '').toLowerCase();
            const start = meta.start ? new Date(meta.start) : null;
            if (!start) return;
            const now = new Date();
            const msToStart = start.getTime() - now.getTime();
            if (status === 'accepted' && msToStart >= 0 && msToStart <= (5 * 60 * 1000)) {
                enableBtn();
                setStatus('Listo — puedes iniciar la cita');
                stopPolling();
                return;
            }

            const targetEnableTs = start.getTime() - (5 * 60 * 1000);
            const startPollingTs = start.getTime() - (startPollingBeforeMinutes * 60 * 1000);
            if (now.getTime() >= startPollingTs && now.getTime() < targetEnableTs) {
                startCountdown(targetEnableTs);
            } else if (now.getTime() < startPollingTs) {
                const mins = Math.ceil((startPollingTs - now.getTime())/60000);
                setStatus(`Comprobaciones empezarán en ${mins} min`);
            }

            if (now.getTime() > start.getTime() + (60 * 60 * 1000)) { stopPolling(); }
        };
        await doPoll();
        pollHandle = setInterval(doPoll, 10000);
    }
    function stopPolling(){ if (pollHandle) { clearInterval(pollHandle); pollHandle = null; } }

    (async function scheduleChecks(){
        const meta = await fetchMeta();
        if (!meta || !meta.ok || !meta.start) return;
        const start = new Date(meta.start);
        const now = new Date();
        if (!isSameLocalDay(now, start)) {
            const dailyCheckMs = 24 * 60 * 60 * 1000;
            const intervalId = setInterval(async function(){
                const m = await fetchMeta();
                if (!m || !m.ok || !m.start) return;
                const s = new Date(m.start);
                if (isSameLocalDay(new Date(), s)) {
                    clearInterval(intervalId);
                    schedulePollingOnDay();
                }
            }, dailyCheckMs);
            return;
        }
        schedulePollingOnDay();
    })();

    function schedulePollingOnDay(){
        (async function(){
            const meta = await fetchMeta();
            if (!meta || !meta.ok || !meta.start) return;
            const start = new Date(meta.start);
            const now = new Date();
            const msToStartMinus = start.getTime() - (startPollingBeforeMinutes*60*1000) - now.getTime();
            if (msToStartMinus <= 0) {
                startPolling();
            } else {
                setTimeout(startPolling, msToStartMinus);
                setStatus(`Comprobaciones empezarán en ${Math.ceil(msToStartMinus/60000)} min`);
            }
        })();
    }
})();
</script>
