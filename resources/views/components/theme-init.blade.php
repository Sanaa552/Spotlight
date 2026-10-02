<script>
    try {
        document.documentElement.dataset.theme = localStorage.getItem('spotlight-theme') === 'light' ? 'light' : 'dark';
    } catch {
        document.documentElement.dataset.theme = 'dark';
    }
</script>
