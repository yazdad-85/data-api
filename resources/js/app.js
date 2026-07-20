import '@fontsource-variable/source-serif-4';
import '@fontsource-variable/source-sans-3';
import '../css/app.css';

document.addEventListener('click', (event) => {
    const toggle = event.target.closest('[data-sidebar-toggle]');
    if (toggle) {
        document.documentElement.classList.toggle('sidebar-open');
        return;
    }
    const backdrop = event.target.closest('[data-sidebar-backdrop]');
    if (backdrop) {
        document.documentElement.classList.remove('sidebar-open');
        return;
    }
    const opener = event.target.closest('[data-modal-open]');
    if (opener) {
        const modal = document.getElementById(opener.dataset.modalOpen);
        if (modal && typeof modal.showModal === 'function') {
            modal.showModal();
        }
        return;
    }
    const closer = event.target.closest('[data-modal-close]');
    if (closer) {
        const modal = closer.closest('dialog');
        if (modal && typeof modal.close === 'function') {
            modal.close();
        }
        return;
    }
    const copyTrigger = event.target.closest('[data-copy-target]');
    if (copyTrigger) {
        const target = document.getElementById(copyTrigger.dataset.copyTarget);
        if (target && navigator.clipboard) {
            navigator.clipboard.writeText(target.value).then(() => {
                const original = copyTrigger.textContent;
                copyTrigger.textContent = 'Disalin!';
                setTimeout(() => {
                    copyTrigger.textContent = original;
                }, 1500);
            }).catch(() => {});
        }
    }
});
