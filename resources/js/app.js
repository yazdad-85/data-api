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
    }
});
