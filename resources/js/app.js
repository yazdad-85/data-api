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
    const selectOnClick = event.target.closest('[data-select-on-click]');
    if (selectOnClick && typeof selectOnClick.select === 'function') {
        selectOnClick.select();
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

document.addEventListener('DOMContentLoaded', () => {
    const authShell = document.querySelector('[data-auth-shell]');
    if (authShell) {
        authShell.classList.add('auth-shell--ready');
    }

    const mutasiSource = document.querySelector('[data-mutasi-source]');
    const mutasiFields = Array.from(document.querySelectorAll('[data-mutasi-field]'));
    if (mutasiSource && mutasiFields.length > 0) {
        const syncMutasiFields = () => {
            const isMutasi = mutasiSource.value === 'mutasi_masuk';
            mutasiFields.forEach((field) => {
                const wrapper = field.closest('.field');
                if (wrapper) {
                    wrapper.hidden = ! isMutasi;
                }
                field.toggleAttribute('required', isMutasi);
            });
        };

        mutasiSource.addEventListener('change', syncMutasiFields);
        syncMutasiFields();
    }
});
