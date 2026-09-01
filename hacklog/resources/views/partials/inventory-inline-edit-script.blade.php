<script>
    document.body.addEventListener('click', function (event) {
        var start = event.target.closest('[data-inline-start]');
        if (!start) {
            return;
        }

        var row = start.closest('[data-inline-edit]');
        if (!row) {
            return;
        }

        var input = row.querySelector('[data-inline-input]');
        if (!input) {
            return;
        }

        row.classList.add('is-editing');
        input.focus();
        input.select();
    });
</script>
