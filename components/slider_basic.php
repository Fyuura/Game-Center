<?php
function render_slider(array $gamesArray, string $heading = '') {
    if (empty($gamesArray)) {
        echo "<p>No games to display in slider.</p>";
        return;
    }

    // benzersiz id (aynı sayfada birden fazla slider varsa çakışmasın)
    $uid = 'slider_' . substr(md5(uniqid((string)mt_rand(), true)), 0, 8);
    ?>
    <div id="<?= $uid ?>" class="slider-container">
        <?php if ($heading): ?>
            <h2 class="slider-heading"><?= htmlspecialchars($heading) ?></h2>
        <?php endif; ?>

        <button class="slider-btn prev" aria-label="Previous">&lt;</button>

        <div class="slider-viewport">
            <div class="slider-window">
                <div class="slider-track">
                <?php foreach ($gamesArray as $game): 
                    $id = (int)($game['id'] ?? 0);
                    $href = "pages/game_details.php?id={$id}";

                    // base price
                    $priceRaw = isset($game['price']) ? (float)$game['price'] : null;

                    // determine discounted price (supports discount_price or discount_percent)
                    $discountPrice = null;
                    if (isset($game['discount_price']) && $game['discount_price'] !== null && $game['discount_price'] !== '') {
                        $discountPrice = (float)$game['discount_price'];
                    } elseif (isset($game['discount_percent']) && $priceRaw !== null) {
                        $pct = (float)$game['discount_percent'];
                        if ($pct > 0 && $pct < 100) {
                            $discountPrice = $priceRaw * (1 - $pct / 100);
                        }
                    }

                    // prepare price HTML (escaped where needed)
                    if ($priceRaw === null) {
                        $priceHtml = '<span class="discount-current">Free</span>';
                    } elseif ($discountPrice !== null && $discountPrice < $priceRaw) {
                        $orig = '$' . number_format($priceRaw, 2);
                        $disc = '$' . number_format($discountPrice, 2);
                        $priceHtml = '<span class="discount-current">' . htmlspecialchars($disc) . '</span>'
                                   . ' <span class="discount-original">' . htmlspecialchars($orig) . '</span>';
                    } else {
                        $priceHtml = '<span class="discount-current">' . htmlspecialchars('$' . number_format($priceRaw, 2)) . '</span>';
                    }
                ?>
                    <a class="slider-item" href="<?= $href ?>">
                        <img src="<?= htmlspecialchars($game['cover_url'] ?? '../assets/images/no-cover.png') ?>"
                             alt="<?= htmlspecialchars($game['title'] ?? '') ?>">
                        <div class="slider-item-price"><?= $priceHtml ?></div>
                        <div class="slider-item-title"><?= htmlspecialchars($game['title'] ?? '') ?></div>
                    </a>
                <?php endforeach; ?>
                </div>
            </div>
        </div>

        <button class="slider-btn next" aria-label="Next">&gt;</button>
    </div>
    <?php
}
?>
