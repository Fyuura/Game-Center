// simple client-side category filter for the library/store pages
document.addEventListener('DOMContentLoaded', function () {
    const select = document.getElementById('categorySelect');
    const container = document.getElementById('gamesContainer');
    if (!select || !container) return;

    const items = Array.from(container.querySelectorAll('.game-list-item, .game-grid-item'));

    function applyFilter() {
        const val = select.value;
        if (val === 'all') {
            items.forEach(i => i.style.display = '');
            return;
        }
        items.forEach(i => {
            const cats = (i.dataset.cats || '').split(',').filter(Boolean);
            const match = cats.includes(val);
            i.style.display = match ? '' : 'none';
        });
    }

    select.addEventListener('change', applyFilter);

    // initialize on load (keeps selection persistent on back/refresh if the select state is preserved)
    applyFilter();
});