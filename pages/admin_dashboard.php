<?php
require '../functions/auth/session_check.php';
require '../db_config.php';

if (isset($_POST['add_category'])) {
    $name = trim($_POST['category_name']);
    if ($name) {
        // Check if category with same name exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE name = ?");
        $stmt->execute([$name]);
        $count = $stmt->fetchColumn();

        if ($count == 0) {
            $stmt = $pdo->prepare("INSERT INTO categories (name) VALUES (?)");
            $stmt->execute([$name]);
        }
    }
    // Reload page after operation (GET)
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

if (isset($_POST['delete_category'])) {
    $id = (int)$_POST['delete_category'];
    $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

if (isset($_POST['approve_game'])) {
    $id = (int)$_POST['approve_game'];
    $stmt = $pdo->prepare("UPDATE games SET status = 'approved' WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

if (isset($_POST['reject_game'])) {
    $id = (int)$_POST['reject_game'];
    $stmt = $pdo->prepare("UPDATE games SET status = 'rejected' WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Fetch categories
$stmt = $pdo->query("
    SELECT * 
    FROM categories 
    ORDER BY name ASC
");

$categories = $stmt->fetchAll();

// Fetch pending games with discount information
$stmt = $pdo->query("
    SELECT g.id, g.title, g.description, g.price, g.created_at, g.developer_id, u.username AS developer_name, 
           GM.cloudinary_url, GD.discount_pct, GD.start_date, GD.end_date
    FROM games g 
    JOIN users u ON g.developer_id = u.id 
    LEFT JOIN game_media GM ON g.id = GM.game_id AND GM.type = 'cover'
    LEFT JOIN game_discounts GD ON g.id = GD.game_id
    WHERE g.status = 'pending' 
    ORDER BY g.created_at DESC
");
$games = $stmt->fetchAll();

// Group games by id and fetch all categories for each game
$pending_games = [];
foreach ($games as $game) {
    $game_id = (int)$game['id'];
    
    if (!isset($pending_games[$game_id])) {
        $pending_games[$game_id] = $game;
        $pending_games[$game_id]['categories'] = [];
        
        // Calculate discounted price if discount is active
        $discount_pct = (int)($game['discount_pct'] ?? 0);
        $start_date = $game['start_date'];
        $end_date = $game['end_date'];
        
        // Check if discount is currently active
        if ($discount_pct > 0 && $start_date && $end_date) {
            $today = date('Y-m-d');
            if ($today >= $start_date && $today <= $end_date) {
                $original_price = (float)$game['price'];
                $pending_games[$game_id]['discounted_price'] = $original_price - ($original_price * $discount_pct / 100);
                $pending_games[$game_id]['active_discount'] = $discount_pct;
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
    $pending_games[$game_id]['categories'] = $cat_stmt->fetchAll();
}

// Reset array keys
$pending_games = array_values($pending_games);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <base href="/gamecenter/">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <link rel="stylesheet" href="assets/css/admin_dashboard.css">
    
</head>

<body>

    <?php include("../components/sidebar.php"); ?>

    <main class="main-content">

        <!-- CATEGORY MANAGEMENT -->
        <section class="category-section">
            <div class="form-container">
                <h1>Categories</h1>
                <!-- Add Category -->
                <form action="" method="POST" class="category-form">
                    <div class="form-group">
                        <input type="text" name="category_name" placeholder="Add new category" required>
                        <button type="submit" name="add_category" class="btn-add">Add</button>
                    </div>
                </form>

                <!-- Category List -->
                <ul class="category-list">
                    <?php foreach ($categories as $cat): ?>
                        <li class="category-item">
                            <span><?= htmlspecialchars($cat['name']) ?></span>
                            <form action="" method="POST" style="display:inline">
                                <button type="submit" name="delete_category" value="<?= $cat['id'] ?>" class="btn-delete">Delete</button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>

        <!-- PENDING GAMES FOR APPROVAL -->
        <section class="pending-games-section">
            <div class="form-container">
                <h1>Pending Game Approvals</h1>

                <?php if (empty($pending_games)): ?>
                    <div style="text-align:center;padding:40px;color:#999;">
                        <i class="fas fa-check-circle" style="font-size:3rem;margin-bottom:15px;display:block;"></i>
                        <p>All games have been reviewed.</p>
                    </div>
                <?php else: ?>
                    <div class="admin-games-list">
                        <?php foreach ($pending_games as $game): ?>
                            <div class="admin-game-item">
                                <!-- Game Cover Image -->
                                <img 
                                    src="<?= htmlspecialchars($game['cloudinary_url'] ?? 'assets/images/no-cover.png') ?>" 
                                    alt="<?= htmlspecialchars($game['title']) ?>"
                                    class="admin-game-cover"
                                >

                                <!-- Game Content -->
                                <div class="admin-game-content">
                                    <div>
                                        <div class="admin-game-header">
                                            <div>
                                                <h3 class="admin-game-title"><?= htmlspecialchars($game['title']) ?></h3>
                                                <p class="admin-game-developer">
                                                    <i class="fas fa-user"></i> <?= htmlspecialchars($game['developer_name']) ?>
                                                </p>
                                            </div>
                                        </div>

                                        <!-- Categories -->
                                        <?php if (!empty($game['categories'])): ?>
                                            <div class="admin-game-categories">
                                                <?php foreach ($game['categories'] as $category): ?>
                                                    <span class="category-badge"><?= htmlspecialchars($category['name']) ?></span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Meta Information -->
                                        <div class="admin-game-meta">
                                            <span><i class="fas fa-dollar-sign"></i> 
                                                <?php if (isset($game['active_discount']) && $game['active_discount'] > 0): ?>
                                                    <span class="original-price">$<?= number_format((float)$game['price'], 2) ?></span>
                                                    <span class="discounted-price">$<?= number_format($game['discounted_price'], 2) ?></span>
                                                    <span class="discount-badge">-<?= $game['active_discount'] ?>%</span>
                                                <?php else: ?>
                                                    $<?= number_format((float)$game['price'], 2) ?>
                                                <?php endif; ?>
                                            </span>
                                            <span><i class="fas fa-calendar"></i> <?= date('d.m.Y H:i', strtotime($game['created_at'])) ?></span>
                                        </div>

                                        <!-- Description -->
                                        <?php if ($game['description']): ?>
                                            <p style="color:#ccc;font-size:0.9rem;margin:10px 0;line-height:1.4;">
                                                <?= htmlspecialchars(substr($game['description'], 0, 150)) ?>...
                                            </p>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Actions -->
                                    <div class="admin-game-actions">
                                        <form action="" method="POST" style="display:inline">
                                            <button type="submit" name="approve_game" value="<?= (int)$game['id'] ?>" class="btn-action btn-approve">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                        </form>
                                        <form action="" method="POST" style="display:inline">
                                            <button type="submit" name="reject_game" value="<?= (int)$game['id'] ?>" class="btn-action btn-reject">
                                                <i class="fas fa-times"></i> Reject
                                            </button>
                                        </form>
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