document.addEventListener('DOMContentLoaded', function() {
    const listBtn = document.getElementById('listViewBtn');
    const gridBtn = document.getElementById('gridViewBtn');
    const gamesContainer = document.getElementById('gamesContainer');

    // Başlangıçta list view aktif olsun
    gamesContainer.classList.add('list-view');

    listBtn.addEventListener('click', function() {
        gamesContainer.classList.add('list-view');
        gamesContainer.classList.remove('grid-view');
        listBtn.classList.add('active');
        gridBtn.classList.remove('active');
    });

    gridBtn.addEventListener('click', function() {
        gamesContainer.classList.add('grid-view');
        gamesContainer.classList.remove('list-view');
        gridBtn.classList.add('active');
        listBtn.classList.remove('active');
    });
});
