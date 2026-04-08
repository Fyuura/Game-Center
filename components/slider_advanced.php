<?php
/**
 * Slider Advanced Component
 *
 * Usage:
 *   render_slider_advanced([
 *     [ 'type' => 'image', 'src' => '...', 'alt' => 'optional' ],
 *     [ 'type' => 'video', 'src' => '...', 'poster' => 'optional', 'alt' => 'optional' ],
 *   ], 'Başlık (isteğe bağlı)');
 *
 * Requirements:
 * - Include assets/css/slider_advanced.css
 * - Include assets/js/slider_advanced.js
 */

if (!function_exists('render_slider_advanced')) {
    function render_slider_advanced(array $mediaItems): void
    {
        if (empty($mediaItems)) {
            echo '<p>Gösterilecek medya bulunamadı.</p>';
            return;
        }

        $uid = 'slider_advanced_' . substr(md5(uniqid((string)mt_rand(), true)), 0, 8);

        $initialIndex = 0;
        foreach ($mediaItems as $idx => $item) {
            if (!empty($item['src'])) { $initialIndex = $idx; break; }
        }
        ?>
        <div id="<?= $uid ?>" class="slider-advanced" data-initial-index="<?= (int)$initialIndex ?>">
        
            <button class="slider-advanced__nav slider-advanced__nav--prev" aria-label="Önceki">&#10094;</button>
            <button class="slider-advanced__nav slider-advanced__nav--next" aria-label="Sonraki">&#10095;</button>

            <div class="slider-advanced__main">
                <?php
                $item = $mediaItems[$initialIndex] ?? null;
                if ($item) {
                    $type = strtolower((string)($item['type'] ?? 'preview_image'));
                    $src = (string)($item['src'] ?? '');
                    $alt = htmlspecialchars((string)($item['alt'] ?? ''));
                    if ($type === 'video') {
                        $poster = htmlspecialchars((string)($item['poster'] ?? ''));
                        ?>
                        <video class="slider-advanced__main-media" controls playsinline <?= $poster ? 'poster="'.$poster.'"' : '' ?>>
                            <source src="<?= htmlspecialchars($src) ?>" />
                            Tarayıcınız video etiketini desteklemiyor.
                        </video>
                        <?php
                    } else {
                        ?>
                        <img class="slider-advanced__main-media" src="<?= htmlspecialchars($src) ?>" alt="<?= $alt ?>" />
                        <?php
                    }
                }
                ?>
            </div>

            <div class="slider-advanced__thumbs">
                <?php foreach ($mediaItems as $i => $m):
                    $isActive = $i === $initialIndex;
                    $type = strtolower((string)($m['type'] ?? 'image'));
                    $src = (string)($m['src'] ?? '');
                    $alt = htmlspecialchars((string)($m['alt'] ?? ''));
                    $poster = htmlspecialchars((string)($m['poster'] ?? ''));
                    ?>
                    <button class="slider-advanced__thumb<?= $isActive ? ' is-active' : '' ?>" data-index="<?= (int)$i ?>" data-type="<?= htmlspecialchars($type) ?>" data-src="<?= htmlspecialchars($src) ?>" data-poster="<?= $poster ?>" aria-label="Öğe <?= (int)$i + 1 ?>">
                        <?php if ($type === 'video'): ?>
                            <?php if ($poster): ?>
                                <img src="<?= $poster ?>" alt="<?= $alt ?>" />
                                <span class="slider-advanced__thumb-badge">▶</span>
                            <?php else: ?>
                                <div class="slider-advanced__thumb-video">
                                    <span>Video</span>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <img src="<?= htmlspecialchars($src) ?>" alt="<?= $alt ?>" />
                        <?php endif; ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }
}
?>


