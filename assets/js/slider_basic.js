document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll(".slider-container").forEach(container => {
        const viewport = container.querySelector(".slider-viewport");
        const windowEl = container.querySelector(".slider-window");
        const track = container.querySelector(".slider-track");
        const prevBtn = container.querySelector(".prev");
        const nextBtn = container.querySelector(".next");

        const items = Array.from(track.children);
        const numItems = items.length;
        let position = 0;
        let baseItemWidth = 0;
        let itemMarginRight = 0;
        let stepWidth = 0;
        let totalTrackWidth = 0;
        let visibleItems = 1;

        function calculate() {
            const first = track.querySelector(".slider-item");
            if (!first) return;

            const style = getComputedStyle(first);
            const w = first.getBoundingClientRect().width;
            const mr = parseFloat(style.marginRight) || 0;
            baseItemWidth = w;
            itemMarginRight = mr;

            // görünür öğe sayısını çöz: N*W + (N-1)*MR <= viewportWidth
            const vpw = viewport.clientWidth;
            visibleItems = Math.floor((vpw + itemMarginRight) / (baseItemWidth + itemMarginRight));
            if (!Number.isFinite(visibleItems)) visibleItems = 1;
            visibleItems = Math.max(1, Math.min(5, Math.min(visibleItems, numItems)));

            // Adım genişliği: N*W + (N-1)*MR
            stepWidth = (baseItemWidth * visibleItems) + (itemMarginRight * Math.max(0, visibleItems - 1));

            // Pencere genişliğini tam adım genişliğine sabitle (peeking engelle) ve ortala
            windowEl.style.width = `${stepWidth}px`;
            windowEl.style.margin = "0 auto";

            // Track genişliğini tüm öğelerin toplam genişliğine ayarla
            totalTrackWidth = (baseItemWidth * numItems) + (itemMarginRight * Math.max(0, numItems - 1));
            track.style.width = `${totalTrackWidth}px`;

            // Scroll yoksa ortala
            if (numItems <= visibleItems) {
                // tüm öğeler sığıyorsa ortala
                viewport.style.paddingLeft = "0px";
                viewport.style.paddingRight = "0px";
                track.style.paddingLeft = "0px";
                track.style.paddingRight = "0px";
                container.classList.add("no-scroll");
                position = 0;
                track.style.transform = `translateX(0px)`;
            } else {
                // scroll varsa paddingleri temizle
                track.style.paddingLeft = "0px";
                track.style.paddingRight = "0px";
                viewport.style.paddingLeft = "0px";
                viewport.style.paddingRight = "0px";
                container.classList.remove("no-scroll");

                // Pozisyonu adımlara göre hizala ve sınırla
                const step = stepWidth;
                // mevcut pozisyonu adıma hizala (drift önle)
                position = Math.round(position / step) * step;

                // clamp to bounds
                const maxNegative = -(totalTrackWidth - stepWidth);
                if (position < maxNegative) position = maxNegative;
                if (position > 0) position = 0;
                position = Math.round(position);
                track.style.transform = `translate3d(${position}px, 0, 0)`;
            }

            // Butonları pencere kenarlarına simetrik hizala
            const containerWidth = container.clientWidth;
            const windowWidth = stepWidth;
            const sideGap = Math.max(0, (containerWidth - windowWidth) / 2);
            const btnInset = 10; // pencere içinden 10px boşluk

            // Prev: sol kenardan
            prevBtn.style.left = `${sideGap + btnInset}px`;
            prevBtn.style.right = "auto";

            // Next: sağ kenardan
            nextBtn.style.right = `${sideGap + btnInset}px`;
            nextBtn.style.left = "auto";
        }

        function next() {
            if (container.classList.contains("no-scroll")) return;

            const step = stepWidth;
            const maxPosition = -(totalTrackWidth - stepWidth);
            // mevcut pozisyonu önce adıma hizala
            position = Math.round(position / step) * step;
            position -= step;
            if (position < maxPosition) position = maxPosition;
            position = Math.round(position);
            track.style.transform = `translate3d(${position}px, 0, 0)`;
        }

        function prev() {
            if (container.classList.contains("no-scroll")) return;

            const step = stepWidth;
            // mevcut pozisyonu önce adıma hizala
            position = Math.round(position / step) * step;
            position += step;
            if (position > 0) position = 0;
            position = Math.round(position);
            track.style.transform = `translate3d(${position}px, 0, 0)`;
        }


        prevBtn.addEventListener("click", prev);
        nextBtn.addEventListener("click", next);

        window.addEventListener("resize", calculate);

        // resimler yüklenince hesapla
        const imgs = track.querySelectorAll("img");
        let loaded = 0;
        if (imgs.length === 0) calculate();
        else {
            imgs.forEach(img => {
                if (img.complete) loaded++;
                else {
                    img.addEventListener("load", () => { loaded++; if (loaded === imgs.length) calculate(); });
                    img.addEventListener("error", () => { loaded++; if (loaded === imgs.length) calculate(); });
                }
            });
            setTimeout(calculate, 200);
        }
    });
});
