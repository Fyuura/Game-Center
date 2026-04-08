<?php
require '../functions/auth/session_check.php';
require '../db_config.php';

// Ensure session is available (session_check.php usually does this)
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Determine current developer id from session (common session shapes)
$developer_id = null;
if (isset($_SESSION['user']['id'])) {
    $developer_id = (int)$_SESSION['user']['id'];
} elseif (isset($_SESSION['user_id'])) {
    $developer_id = (int)$_SESSION['user_id'];
} elseif (isset($_SESSION['id'])) {
    $developer_id = (int)$_SESSION['id'];
}

if (!$developer_id) {
    // Not authenticated or unexpected session shape — redirect to login
    header("Location: /gamecenter/login.php");
    exit;
}

// Handle deletion of a developer's own game — only allow deleting rejected games
if (isset($_POST['delete_game'])) {
    $id = (int)$_POST['delete_game'];

    // Only delete if this game belongs to the developer and is rejected
    $stmt = $pdo->prepare("DELETE FROM games WHERE id = ? AND developer_id = ? AND status = 'rejected'");
    $stmt->execute([$id, $developer_id]);

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Fetch games submitted by this developer with all their categories and discounts
$stmt = $pdo->prepare("
    SELECT G.id, G.title, G.status, G.price, G.created_at, G.developer_id, GM.cloudinary_url,
           GD.discount_pct, GD.start_date, GD.end_date
    FROM games G
    LEFT JOIN game_media GM ON G.id = GM.game_id AND GM.type = 'cover'
    LEFT JOIN game_discounts GD ON G.id = GD.game_id
    WHERE G.developer_id = ?
    ORDER BY G.created_at DESC
");
$stmt->execute([$developer_id]);
$games = $stmt->fetchAll();

// Group games by id and fetch all categories for each game
$my_games = [];
foreach ($games as $game) {
    $game_id = (int)$game['id'];
    
    if (!isset($my_games[$game_id])) {
        $my_games[$game_id] = $game;
        $my_games[$game_id]['categories'] = [];
        
        // Calculate discounted price if discount is active
        $discount_pct = (int)($game['discount_pct'] ?? 0);
        $start_date = $game['start_date'];
        $end_date = $game['end_date'];
        
        // Check if discount is currently active
        if ($discount_pct > 0 && $start_date && $end_date) {
            $today = date('Y-m-d');
            if ($today >= $start_date && $today <= $end_date) {
                $original_price = (float)$game['price'];
                $my_games[$game_id]['discounted_price'] = $original_price - ($original_price * $discount_pct / 100);
                $my_games[$game_id]['active_discount'] = $discount_pct;
            }
        }
    }
    
    // Fetch all categories for this game
    $cat_stmt = $pdo->prepare("
        SELECT C.id, C.name
        FROM categories C
        INNER JOIN game_categories GC ON C.id = GC.category_id
        WHERE GC.game_id = ?
    ");
    $cat_stmt->execute([$game_id]);
    $my_games[$game_id]['categories'] = $cat_stmt->fetchAll();
}

// Reset array keys
$my_games = array_values($my_games);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Developer Dashboard</title>
    <base href="/gamecenter/">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <link rel="stylesheet" href="assets/css/admin_dashboard.css">
    <link rel="stylesheet" href="assets/css/game_listview.css">
    <link rel="stylesheet" href="assets/css/dev_dashboard.css">
</head>

<body>

    <?php include("../components/sidebar.php"); ?>

    <main class="main-content">

        <section class="pending-games-section">
            <div class="form-container">
                <h1>Submitted Games</h1>

                <?php if (empty($my_games)): ?>
                    <div style="text-align:center;padding:40px;color:#999;">
                        <i class="fas fa-inbox" style="font-size:3rem;margin-bottom:15px;display:block;"></i>
                        <p>No game has been submitted yet.</p>
                    </div>
                <?php else: ?>
                    <div class="dev-games-list">
                        <?php foreach ($my_games as $game): ?>
                            <div class="dev-game-item">
                                <!-- Game Cover Image -->
                                <img 
                                    src="<?= htmlspecialchars($game['cloudinary_url'] ?? 'assets/images/no-cover.png') ?>" 
                                    alt="<?= htmlspecialchars($game['title']) ?>"
                                    class="dev-game-cover"
                                >

                                <!-- Game Content -->
                                <div class="dev-game-content">
                                    <div class="dev-game-header">
                                        <h3 class="dev-game-title"><?= htmlspecialchars($game['title']) ?></h3>
                                        <span class="dev-game-status status-<?= htmlspecialchars($game['status']) ?>">
                                            <?= htmlspecialchars($game['status']) ?>
                                        </span>
                                    </div>

                                    <!-- Categories -->
                                    <?php if (!empty($game['categories'])): ?>
                                        <div class="dev-game-categories">
                                            <?php foreach ($game['categories'] as $category): ?>
                                                <span class="category-badge"><?= htmlspecialchars($category['name']) ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Meta Information -->
                                    <div class="dev-game-meta">
                                        <span><i class="fas fa-dollar-sign"></i> 
                                            <?php if (isset($game['active_discount']) && $game['active_discount'] > 0): ?>
                                                <span class="original-price">$<?= number_format((float)$game['price'], 2) ?></span>
                                                <span class="discounted-price">$<?= number_format($game['discounted_price'], 2) ?></span>
                                                <span class="discount-badge">-<?= $game['active_discount'] ?>%</span>
                                            <?php else: ?>
                                                $<?= number_format((float)$game['price'], 2) ?>
                                            <?php endif; ?>
                                        </span>
                                        <span><i class="fas fa-calendar"></i> <?= date('d.m.Y', strtotime($game['created_at'])) ?></span>
                                    </div>

                                    <!-- Actions -->
                                    <div class="dev-game-actions">
                                        <?php if (($game['status'] ?? '') === 'approved'): ?>
                                            <!-- For approved games allow adding a discount -->
                                            <a class="btn-action btn-add-discount" href="pages/add_discount.php?id=<?= (int)$game['id'] ?>">
                                                <i class="fas fa-tag"></i> Add Discount
                                            </a>
                                        <?php elseif (($game['status'] ?? '') === 'rejected'): ?>
                                            <!-- For rejected games allow deletion -->
                                            <form action="" method="POST" onsubmit="return confirm('Are you sure you want to delete this game? This action cannot be undone.');" style="display:inline">
                                                <button type="submit" name="delete_game" value="<?= (int)$game['id'] ?>" class="btn-action btn-delete">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <!-- For pending games no operations allowed -->
                                            <span class="no-action">
                                                <i class="fas fa-lock"></i> Awaiting review
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

    </main>

</body>

</html>