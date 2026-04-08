<?php
function render_game_grid(array $games, bool $showPrice = true, array $gameCategories = [])
{
    if (empty($games)) {
        echo "<p>No games found.</p>";
        return;
    }

    // build map: game_id => [category_id, ...]
    $catMap = [];
    foreach ($gameCategories as $gc) {
        $gid = (int)($gc['game_id'] ?? 0);
        $cid = (int)($gc['category_id'] ?? 0);
        if ($gid && $cid) $catMap[$gid][] = $cid;
    }

    echo '<div class="game-grid">';
    foreach ($games as $game) {
        $cover = htmlspecialchars($game['cover_url'] ?? 'assets/images/no-cover.png');
        $title = htmlspecialchars($game['title']);
        $id = (int)($game['id'] ?? 0);

        $cats = $catMap[$id] ?? [];
        $catsAttr = htmlspecialchars(implode(',', $cats));

        $priceMarkup = '';
        if ($showPrice) {
            // base price
            $priceRaw = isset($game['price']) && $game['price'] !== null ? (float)$game['price'] : null;
            $discount_pct = isset($game['discount_percent']) ? (float)$game['discount_percent'] : 0;

            // calculate discounted price if applicable
            if ($priceRaw === null || $priceRaw <= 0) {
                $priceHtml = '<span class="discount-current">Free</span>';
            } elseif ($discount_pct > 0 && $discount_pct < 100) {
                $discounted = $priceRaw * (1 - $discount_pct / 100);
                $orig = '$' . number_format($priceRaw, 2);
                $disc = '$' . number_format($discounted, 2);
                $priceHtml = '<span class="discount-current">' . htmlspecialchars($disc) . '</span> '
                           . '<span class="discount-original">' . htmlspecialchars($orig) . '</span>';
            } else {
                $priceHtml = '<span class="discount-current">' . htmlspecialchars('$' . number_format($priceRaw, 2)) . '</span>';
            }

            $priceMarkup = '<div class="game-price">' . $priceHtml . '</div>';
        }

        $href = "pages/game_details.php?id={$id}";
        echo <<<HTML
            <a class="game-grid-item" href="$href" data-cats="$catsAttr">
                <img src="$cover" alt="$title">
                <div class="game-info">
                    <h3>$title</h3>
                    {$priceMarkup}
                </div>
            </a>
            HTML;
    }
    echo '</div>';
}
?>
