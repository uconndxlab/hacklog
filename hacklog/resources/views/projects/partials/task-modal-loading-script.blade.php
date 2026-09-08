<script>
(function () {
    const loadingMarkup = `
        <div class="text-center py-4" style="flex: 1; display: flex; align-items: center; justify-content: center;">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading task...</span>
            </div>
        </div>`;

    document.body.addEventListener('htmx:beforeRequest', function (event) {
        const trigger = event.detail.elt;

        if (!(trigger instanceof Element) || !trigger.matches('[data-task-modal-load]')) {
            return;
        }

        const content = document.getElementById('taskModalContent');
        if (!content) return;

        // The modal opens immediately, before HTMX receives the next task. Remove
        // the previous task (including its active tab) before the browser paints.
        content.setAttribute('aria-busy', 'true');
        content.innerHTML = loadingMarkup;

        const title = document.getElementById('taskModalLabel');
        if (title) title.textContent = 'Task';

        ['taskModalIdentifier', 'taskModalProject', 'taskModalActions'].forEach(function (id) {
            const element = document.getElementById(id);
            if (!element) return;
            element.replaceChildren();
            element.style.display = 'none';
        });
    });

    document.body.addEventListener('htmx:afterSwap', function (event) {
        if (event.detail.target.id === 'taskModalContent') {
            event.detail.target.removeAttribute('aria-busy');
        }
    });
})();
</script>
