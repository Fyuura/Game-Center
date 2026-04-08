<?php
require '../db_config.php';
require '../functions/auth/session_check.php';

if (!isset($_GET['id'])) {
    echo "Game ID not specified!";
    exit;
}

$game_id = (int)$_GET['id'];

try {
    $stmt = $pdo->prepare("
        SELECT * 
        FROM games 
        WHERE id = :id AND status = 'approved'
    ");
    $stmt->execute([
        ':id' => $game_id
    ]);
    $game = $stmt->fetch();

    if (!$game) {
        echo "Game not found!";
        exit;
    }

    // Fetch discount if exists and is active
    $stmt = $pdo->prepare("
        SELECT discount_pct, start_date, end_date
        FROM game_discounts
        WHERE game_id = :game_id AND start_date <= CURDATE() AND end_date >= CURDATE()
    ");
    $stmt->execute([
        ':game_id' => $game_id
    ]);
    $discount = $stmt->fetch();

    // Calculate discounted price
    $original_price = (float)$game['price'];
    $discounted_price = $original_price;
    $discount_pct = 0;

    if ($discount) {
        $discount_pct = (int)$discount['discount_pct'];
        $discounted_price = $original_price - ($original_price * $discount_pct / 100);
    }

    $stmt = $pdo->prepare("
        SELECT *
        FROM game_media 
        WHERE game_id = :id AND (type = 'preview_image' OR type = 'video') 
    ");
    $stmt->execute([
        ':id' => $game_id
    ]);
    $mediaArray = $stmt->fetchAll();

    // Map DB rows to slider_advanced expected structure
    $mediaItems = [];
    foreach ($mediaArray as $i => $row) {
        $src = $row['cloudinary_url'] ?? ($row['url'] ?? '');
        if (!$src) continue;
        $type = strtolower($row['type'] ?? 'preview_image');
        $mediaItems[] = [
            'type' => $type === 'video' ? 'video' : 'image',
            'src' => $src,
            'poster' => $row['poster_url'] ?? ($row['thumbnail_url'] ?? ''),
            'alt' => ($game['title'] ?? 'Media') . ' #' . ($i + 1),
        ];
    }

    $stmt = $pdo->prepare("
        SELECT cloudinary_url
        FROM game_media
        WHERE game_id = :game_id AND type = 'banner'
    ");
    $stmt->execute([
        ':game_id' => $game_id
    ]);
    $banner = $stmt->fetch();

    $stmt = $pdo->prepare("
        SELECT D.username, D.id
        FROM users D
        JOIN games G on D.id = G.developer_id
        WHERE G.id = :game_id
    ");
    $stmt->execute([
        ':game_id' => $game_id
    ]);
    $devinfo = $stmt->fetch();

    $stmt = $pdo->prepare("
        SELECT *
        FROM categories
        JOIN game_categories GC ON categories.id = GC.category_id
        WHERE GC.game_id = :id
    ");
    $stmt->execute([
        ':id' => $game_id
    ]);
    $categories = $stmt->fetchAll();

    $stmt = $pdo->prepare("
        SELECT *
        FROM game_requirements
        WHERE game_id = :id
    ");
    $stmt->execute([
        ':id' => $game_id
    ]);
    $reqs = $stmt->fetchAll();

    $minReq = [];
    $recReq = [];

    foreach ($reqs as $req) {
        if ($req['type'] === 'minimum') {
            $minReq = $req;
        } elseif ($req['type'] === 'recommended') {
            $recReq = $req;
        }
    }
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($game['title']) ?> Details</title>
    <base href="/gamecenter/">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <link rel="stylesheet" href="assets/css/slider_advanced.css">
    <link rel="stylesheet" href="assets/css/game_details.css">
    <link rel="stylesheet" href="assets/css/cart_btn.css">
</head>

<body>
    <?php include("../components/sidebar.php"); ?>

    <?php
    include("../components/cart_btn.php");
    renderCartButton();
    ?>

    <main class="main-content">
        <h1><?php echo htmlspecialchars($game['title']); ?></h1>

        <div class="preview-container">
            <div class="slider-container">
                <?php
                // Slider
                include '../components/slider_advanced.php';
                render_slider_advanced($mediaItems);
                ?>
            </div>
            <div class="glance">
                <div class="banner">
                    <img src="<?= htmlspecialchars($banner['cloudinary_url']) ?>"
                        alt="<?= htmlspecialchars($game['title']) ?> banner">
                </div>
                <div class="game-info">
                    <p class="dev-info">
                        Made By: <?php echo htmlspecialchars($devinfo['username']) ?>
                    </p>
                    <p class="publish-date">
                        Publishing Date: <?php echo htmlspecialchars($game['created_at']) ?>
                    </p>
                </div>
                <div class="game-categories">
                    <?php foreach ($categories as $cat): ?>
                        <div class="tags"><?php echo htmlspecialchars($cat['name']) ?></div>
                    <?php endforeach; ?>
                </div>
                <div class="game-purchase">
                    <div class="price">
                        <?php if ($game['price'] > 0): ?>
                            <span class="price-amount">
                                <?php if ($discount_pct > 0): ?>
                                    <span class="original-price">$<?php echo number_format($original_price, 2) ?></span>
                                    <span class="discounted-price">$<?php echo number_format($discounted_price, 2) ?></span>
                                    <span class="discount-badge">-<?php echo $discount_pct ?>%</span>
                                <?php else: ?>
                                    $<?php echo number_format($original_price, 2) ?>
                                <?php endif; ?>
                            </span>
                        <?php else: ?>
                            <span class="price-amount">Free</span>
                        <?php endif; ?>
                    </div>
                    <div class="addToCart-btn">
                        <?php if ($game['price'] > 0): ?>
                            <button class="btn btn-primary" onclick="location.href='pages/cart.php?action=add&id=<?php echo $game['id']; ?>'">
                                <i class="fa fa-shopping-cart"></i> Add to Cart
                            </button>
                        <?php else: ?>
                            <button class="btn btn-secondary" onclick="location.href='pages/cart.php?action=add&id=<?php echo $game['id']; ?>'">
                                <i class="fa fa-square"></i> Get for Free
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="description-container">
            <div class="game-description">
                <?php echo htmlspecialchars($game['description']) ?>
            </div>
        </div>

        <div class="req-container">
            <h2>System Requirements</h2>
            <div class="req-cols">
                <div class="req-min">
                    <h3>Minimum</h3>
                    <ul>
                        <li>Operating System: <?php echo htmlspecialchars($minReq['os'] ?? 'N/A') ?></li>
                        <li>Processor: <?php echo htmlspecialchars($minReq['processor'] ?? 'N/A') ?></li>
                        <li>RAM: <?php echo htmlspecialchars($minReq['memory'] ?? 'N/A') ?></li>
                        <li>Graphics Card: <?php echo htmlspecialchars($minReq['graphics'] ?? 'N/A') ?></li>
                        <li>Storage: <?php echo htmlspecialchars($minReq['storage'] ?? 'N/A') ?></li>
                    </ul>
                    <?php if (!empty($minReq['other'])): ?>
                        <h4>Other:</h4>
                        <p><?php echo htmlspecialchars($minReq['other']) ?></p>
                    <?php endif; ?>
                </div>
                <div class="req-rec">
                    <h3>Recommended</h3>
                    <ul>
                        <li>Operating System: <?php echo htmlspecialchars($recReq['os'] ?? 'N/A') ?></li>
                        <li>Processor: <?php echo htmlspecialchars($recReq['processor'] ?? 'N/A') ?></li>
                        <li>RAM: <?php echo htmlspecialchars($recReq['memory'] ?? 'N/A') ?></li>
                        <li>Graphics Card: <?php echo htmlspecialchars($recReq['graphics'] ?? 'N/A') ?></li>
                        <li>Storage: <?php echo htmlspecialchars($recReq['storage'] ?? 'N/A') ?></li>
                    </ul>
                    <?php if (!empty($recReq['other'])): ?>
                        <h4>Other:</h4>
                        <p><?php echo htmlspecialchars($recReq['other']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <script src="assets/js/slider_advanced.js" defer></script>
    </main>
</body>

</html>