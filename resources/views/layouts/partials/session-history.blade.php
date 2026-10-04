<script>
    // History snapshots bypass the server: reload to check the current session.
    window.addEventListener('pagehide', function (event) {
        if (event.persisted) {
            document.documentElement.style.visibility = 'hidden';
        }
    });
    window.addEventListener('pageshow', function (event) {
        if (event.persisted) {
            document.documentElement.style.visibility = 'hidden';
            window.location.reload();
        }
    });
</script>
