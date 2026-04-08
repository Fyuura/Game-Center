<?php
require 'functions/auth/session_check.php';
require 'db_config.php';
require 'cloudinary_config.php';

$errors = [];

try {
    $stmt = $pdo->prepare("
        SELECT balance
        FROM users
        WHERE id = :user_id
    ");
    $stmt->execute(['user_id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();

    $stmt = $pdo->query("
        SELECT G.id, G.title, G.price, M.cloudinary_url as cover_url,
               D.discount_pct AS discount_percent
        FROM games G
        LEFT JOIN game_media M ON G.id = M.game_id AND M.type = 'cover'
        LEFT JOIN (
            SELECT game_id, discount_pct
            FROM game_discounts
            WHERE start_date <= CURDATE() AND end_date >= CURDATE()
        ) D ON D.game_id = G.id
        WHERE G.status = 'approved'
    ");
    $games = $stmt->fetchAll();

    // Query for discounted games (for slider)
    $stmt = $pdo->query("
        SELECT G.id, G.title, G.price, M.cloudinary_url as cover_url,
               D.discount_pct AS discount_percent
        FROM games G
        LEFT JOIN game_media M ON G.id = M.game_id AND M.type = 'cover'
        LEFT JOIN (
            SELECT game_id, discount_pct
            FROM game_discounts
            WHERE start_date <= CURDATE() AND end_date >= CURDATE()
        ) D ON D.game_id = G.id
        WHERE G.status = 'approved' AND D.discount_pct IS NOT NULL
        ORDER BY D.discount_pct DESC
    ");
    $discounted_games = $stmt->fetchAll();

    $stmt = $pdo->query("
        SELECT *
        FROM categories
        ORDER BY name
    ");
    $categories = $stmt->fetchAll();

    $stmt = $pdo->query("
        SELECT *
        FROM game_categories
    ");

    $game_categories = $stmt->fetchAll();

} catch (PDOException $e) {
    $errors[] = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Game Center</title>
    <base href="/gamecenter/">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <link rel="stylesheet" href="assets/css/store.css">
    <link rel="stylesheet" href="assets/css/slider_basic.css">
    <link rel="stylesheet" href="assets/css/game_listview.css">
    <link rel="stylesheet" href="assets/css/game_gridview.css">
    <link rel="stylesheet" href="assets/css/cart_btn.css">
    <link rel="stylesheet" href="assets/css/balance_btn.css">

</head>

<body>
    <?php include("components/sidebar.php"); ?>

    <?php 
    include("components/cart_btn.php");
    renderCartButton();
    ?>

    <?php 
    include("components/balance_btn.php");
    renderBalanceButton($user['balance']);
    ?>

    <main class="main-content">
        <h1>Store</h1>

        <?php include('components/alerts.php');
        render_alert($errors);
        ?>

        <?php include('components/slider_basic.php');
        render_slider($discounted_games, 'Discounted Games');
        ?>

        <?php
        include 'components/game_listview.php';
        include 'components/game_gridview.php';
        ?>

        <div class="games-general">
            <div class="view-controls">
                <!-- Kategori seçimi -->
                <select id="categorySelect">
                    <option value="all">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat['id']) ?>">
                            <?php echo htmlspecialchars($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <!-- Toggle butonları -->
                <div class="view-toggle">
                    <button id="listViewBtn" class="active"><i class="fas fa-list"></i></button>
                    <button id="gridViewBtn"><i class="fas fa-th-large"></i></button>
                </div>
            </div>

            <div id="gamesContainer">
                <?php render_game_list($games, true, $game_categories); ?>
                <?php render_game_grid($games, true, $game_categories); ?>
            </div>
        </div>

        

    </main>
    <script src="assets/js/view_toggle.js"></script>
    <script src="assets/js/category_filter.js" defer></script>
    <script src="assets/js/slider_basic.js" defer></script>

</body>

</html>