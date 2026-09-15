(function () {
    let activeTrigger = null;

    function ensureDialog() {
        let overlay = document.getElementById('adminAppDialog');
        if (overlay) return overlay;

        overlay = document.createElement('div');
        overlay.id = 'adminAppDialog';
        overlay.className = 'admin-app-dialog';
        overlay.hidden = true;
        overlay.innerHTML = `
            <div class="admin-app-dialog-card" role="dialog" aria-modal="true" aria-labelledby="adminAppDialogTitle" aria-describedby="adminAppDialogMessage" tabindex="-1">
                <div class="admin-app-dialog-icon"><i class="fas fa-circle-question" aria-hidden="true"></i></div>
                <div class="admin-app-dialog-copy">
                    <h2 id="adminAppDialogTitle">Please confirm</h2>
                    <p id="adminAppDialogMessage"></p>
                    <label class="admin-app-dialog-field" hidden><span></span><input type="text" autocomplete="off"></label>
                </div>
                <div class="admin-app-dialog-actions">
                    <button type="button" class="btn btn-secondary" data-dialog-cancel>Cancel</button>
                    <button type="button" class="btn btn-primary" data-dialog-confirm>Continue</button>
                </div>
            </div>`;
        document.body.appendChild(overlay);
        return overlay;
    }

    function openDialog(message, options) {
        const config = Object.assign({ title: 'Please confirm', confirmText: 'Continue', cancelText: 'Cancel', danger: false, promptLabel: '' }, options || {});
        const overlay = ensureDialog();
        const card = overlay.querySelector('.admin-app-dialog-card');
        const title = overlay.querySelector('#adminAppDialogTitle');
        const copy = overlay.querySelector('#adminAppDialogMessage');
        const field = overlay.querySelector('.admin-app-dialog-field');
        const input = field.querySelector('input');
        const cancel = overlay.querySelector('[data-dialog-cancel]');
        const confirm = overlay.querySelector('[data-dialog-confirm]');

        activeTrigger = document.activeElement;
        title.textContent = config.title;
        copy.textContent = message;
        cancel.textContent = config.cancelText;
        confirm.textContent = config.confirmText;
        confirm.className = `btn ${config.danger ? 'btn-danger' : 'btn-primary'}`;
        field.hidden = !config.promptLabel;
        field.querySelector('span').textContent = config.promptLabel;
        input.value = '';
        overlay.hidden = false;
        document.body.classList.add('modal-open');

        return new Promise(resolve => {
            const close = value => {
                overlay.hidden = true;
                document.body.classList.remove('modal-open');
                document.removeEventListener('keydown', onKeydown);
                activeTrigger?.focus?.();
                resolve(value);
            };
            const onKeydown = event => {
                if (event.key === 'Escape') close(config.promptLabel ? null : false);
                if (event.key === 'Tab') {
                    const focusable = Array.from(card.querySelectorAll('button:not([disabled]), input:not([hidden])')).filter(element => !element.closest('[hidden]'));
                    const first = focusable[0];
                    const last = focusable[focusable.length - 1];
                    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
                    if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
                }
            };
            cancel.onclick = () => close(config.promptLabel ? null : false);
            confirm.onclick = () => close(config.promptLabel ? input.value : true);
            overlay.onclick = event => { if (event.target === overlay) close(config.promptLabel ? null : false); };
            document.addEventListener('keydown', onKeydown);
            requestAnimationFrame(() => (config.promptLabel ? input : confirm).focus());
        });
    }

    window.adminConfirm = (message, options) => openDialog(message, options);
    window.adminPrompt = (message, options) => openDialog(message, Object.assign({}, options, { promptLabel: options?.promptLabel || 'Reason' }));
    window.adminNotify = function (message, type) {
        if (typeof window.showNotification === 'function') {
            window.showNotification(message, type || 'info');
            return;
        }
        const notice = document.createElement('div');
        notice.className = `admin-toast admin-toast-${type || 'info'}`;
        notice.setAttribute('role', type === 'error' ? 'alert' : 'status');
        notice.textContent = message;
        document.body.appendChild(notice);
        requestAnimationFrame(() => notice.classList.add('show'));
        setTimeout(() => notice.remove(), 4500);
    };

    document.addEventListener('submit', async function (event) {
        const form = event.target.closest('form[data-admin-confirm]');
        if (!form || form.dataset.adminConfirmed === '1') return;
        event.preventDefault();
        const submitter = event.submitter;
        const confirmed = await window.adminConfirm(form.dataset.adminConfirm, {
            title: form.dataset.adminConfirmTitle || 'Please confirm',
            confirmText: form.dataset.adminConfirmButton || 'Continue',
            danger: form.dataset.adminConfirmDanger === '1'
        });
        if (!confirmed) return;
        form.dataset.adminConfirmed = '1';
        if (submitter) form.requestSubmit(submitter); else form.requestSubmit();
    });
})();
