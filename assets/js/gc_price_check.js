document.addEventListener('DOMContentLoaded', () => {
    const priceWhole = document.getElementById('price_whole');
    const priceDecimal = document.getElementById('price_decimal');

    if (!priceWhole || !priceDecimal) return; // input yoksa çık

    priceWhole.addEventListener('input', () => {
        const max = parseInt(priceWhole.max);
        if (priceWhole.value === "") return;
        if (parseInt(priceWhole.value) > max) priceWhole.value = max;
        if (parseInt(priceWhole.value) < 0) priceWhole.value = 0;
    });

    priceDecimal.addEventListener('input', () => {
        const max = parseInt(priceDecimal.max);
        const min = parseInt(priceDecimal.min);
        if (priceDecimal.value === "") return;
        if (parseInt(priceDecimal.value) > max) priceDecimal.value = max;
        if (parseInt(priceDecimal.value) < min) priceDecimal.value = min;
    });
});