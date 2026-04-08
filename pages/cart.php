<?php
    require '../functions/auth/session_check.php';
    require '../db_config.php';

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $errors = [];
    $success = [];
    $user_id = $_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? $_SESSION['id'] ?? null;

    if (!$user_id) {
        header("Location: /gamecenter/login.php");
        exit;
    }

    // Handle add to cart via GET
    if (isset($_GET['action']) && $_GET['action'] === 'add' && isset($_GET['id'])) {
        $game_id = (int)$_GET['id'];
        
        try {
            // Check if already in cart
            $stmt = $pdo->prepare("SELECT id FROM cart WHERE user_id = ? AND game_id = ?");
            $stmt->execute([$user_id, $game_id]);
            
            if (!$stmt->fetch()) {
                // Check if game exists and is approved
                $stmt = $pdo->prepare("SELECT id FROM games WHERE id = ? AND status = 'approved'");
                $stmt->execute([$game_id]);
                
                if ($stmt->fetch()) {
                    // Add to cart
                    $stmt = $pdo->prepare("INSERT INTO cart (user_id, game_id) VALUES (?, ?)");
                    $stmt->execute([$user_id, $game_id]);
                }
            }
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
        
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    // Handle remove from cart
    if (isset($_POST['remove_game'])) {
        $game_id = (int)$_POST['remove_game'];
        
        try {
            $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ? AND game_id = ?");
            $stmt->execute([$user_id, $game_id]);
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
        
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    // Handle purchase confirmation
    if (isset($_POST['confirm_purchase'])) {
        try {
            // Get user's current balance
            $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();

            if (!$user) {
                $errors[] = "User not found.";
            } else {
                $balance = (float)$user['balance'];

                // Get all cart items with active discounts
                $stmt = $pdo->prepare("
                    SELECT C.id as cart_id, G.id, G.title, G.price, 
                           GD.discount_pct, GD.start_date, GD.end_date
                    FROM cart C
                    JOIN games G ON C.game_id = G.id
                    LEFT JOIN game_discounts GD ON G.id = GD.game_id
                    WHERE C.user_id = ?
                ");
                $stmt->execute([$user_id]);
                $cart_items = $stmt->fetchAll();

                if (empty($cart_items)) {
                    $errors[] = "Your cart is empty.";
                } else {
                    // Calculate total with discounts
                    $total = 0;
                    $purchased_games = [];

                    foreach ($cart_items as $item) {
                        $price = (float)$item['price'];
                        
                        // Check if discount is active
                        if ($item['discount_pct'] && $item['start_date'] && $item['end_date']) {
                            $today = date('Y-m-d');
                            if ($today >= $item['start_date'] && $today <= $item['end_date']) {
                                $discount_pct = (int)$item['discount_pct'];
                                $price = $price - ($price * $discount_pct / 100);
                            }
                        }
                        
                        $total += $price;
                        $purchased_games[] = [
                            'cart_id' => $item['cart_id'],
                            'game_id' => $item['id'],
                            'title' => $item['title'],
                            'price' => $price
                        ];
                    }

                    // Check if user has sufficient balance
                    if ($balance < $total) {
                        $errors[] = "Insufficient balance. You need $" . number_format($total - $balance, 2) . " more.";
                    } else {
                        // Begin transaction
                        $pdo->beginTransaction();

                        try {
                            // Deduct balance from user
                            $new_balance = $balance - $total;
                            $stmt = $pdo->prepare("UPDATE users SET balance = ? WHERE id = ?");
                            $stmt->execute([$new_balance, $user_id]);

                            // Add games to user_games
                            $stmt = $pdo->prepare("INSERT INTO user_games (user_id, game_id) VALUES (?, ?)");
                            foreach ($purchased_games as $game) {
                                $stmt->execute([$user_id, $game['game_id']]);
                            }

                            // Record transactions
                            $stmt = $pdo->prepare("INSERT INTO transactions (user_id, game_id, amount, type) VALUES (?, ?, ?, 'purchase')");
                            foreach ($purchased_games as $game) {
                                $stmt->execute([$user_id, $game['game_id'], -$game['price']]);
                            }

                            // Remove items from cart
                            $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ?");
                            $stmt->execute([$user_id]);

                            // Commit transaction
                            $pdo->commit();

                            $success[] = "Purchase successful! " . count($purchased_games) . " game(s) added to your library.";
                            header("Location: game_library.php");
                            exit;

                        } catch (PDOException $e) {
                            $pdo->rollBack();
                            
                            // Handle duplicate entry error
                            if ($e->getCode() == 23000) {
                                $errors[] = "You already own one or more games in your cart. Please remove duplicates and try again.";
                            } else {
                                $errors[] = "Transaction error: " . $e->getMessage();
                            }
                        }
                    }
                }
            }
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }

    // Get user balance
    try {
        $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        $balance = (float)($user['balance'] ?? 0);
    } catch (PDOException $e) {
        $errors[] = "Error fetching balance: " . $e->getMessage();
        $balance = 0;
    }

    // Fetch cart items with discount info
    try {
        $stmt = $pdo->prepare("
            SELECT C.id as cart_id, G.id, G.title, G.price, M.cloudinary_url as cover_url,
                   GD.discount_pct, GD.start_date, GD.end_date
            FROM cart C
            JOIN games G ON C.game_id = G.id
            LEFT JOIN game_media M ON G.id = M.game_id AND M.type = 'cover'
            LEFT JOIN game_discounts GD ON G.id = GD.game_id
            WHERE C.user_id = ?
            ORDER BY C.added_at DESC
        ");
        $stmt->execute([$user_id]);
        $cart_items = $stmt->fetchAll();

        // Calculate totals
        $subtotal = 0;
        $total_discount = 0;
        
        foreach ($cart_items as &$item) {
            $price = (float)$item['price'];
            $item['final_price'] = $price;
            $item['discount_applied'] = 0;
            
            // Check if discount is active
            if ($item['discount_pct'] && $item['start_date'] && $item['end_date']) {
                $today = date('Y-m-d');
                if ($today >= $item['start_date'] && $today <= $item['end_date']) {
                    $discount_pct = (int)$item['discount_pct'];
                    $discount_amount = $price * $discount_pct / 100;
                    $item['final_price'] = $price - $discount_amount;
                    $item['discount_applied'] = $discount_pct;
                    $total_discount += $discount_amount;
                }
            }
            
            $subtotal += $price;
        }
        
        $total = $subtotal - $total_discount;

    } catch (PDOException $e) {
        $errors[] = "Database error: " . $e->getMessage();
        $cart_items = [];
        $subtotal = 0;
        $total_discount = 0;
        $total = 0;
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Cart</title>
    <base href="/gamecenter/">


    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <link rel="stylesheet" href="assets/css/cart.css">
    <link rel="stylesheet" href="assets/css/alerts.css">

</head>
<body>
    <?php include("../components/sidebar.php"); ?>

    <main class="main-content">
        <div class="cart-container">
            <h1>Shopping Cart</h1>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <?php foreach ($errors as $error): ?>
                        <div><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <?php foreach ($success as $msg): ?>
                        <div><i class="fas fa-check-circle"></i> <?= htmlspecialchars($msg) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (empty($cart_items)): ?>
                <div class="empty-cart">
                    <i class="fas fa-inbox"></i>
                    <p>Your cart is empty</p>
                    <a href="index.php" class="btn btn-primary">
                        <i class="fas fa-store"></i> Continue Shopping
                    </a>
                </div>
            <?php else: ?>
                <div class="cart-wrapper">
                    <!-- Cart Items -->
                    <div class="cart-items">
                        <?php foreach ($cart_items as $item): ?>
                            <div class="cart-item">
                                <img src="<?= htmlspecialchars($item['cover_url'] ?? 'assets/images/no-cover.png') ?>" 
                                     alt="<?= htmlspecialchars($item['title']) ?>" class="cart-item-cover">
                                
                                <div class="cart-item-info">
                                    <h3><?= htmlspecialchars($item['title']) ?></h3>
                                    
                                    <div class="cart-item-price">
                                        <?php if ($item['discount_applied'] > 0): ?>
                                            <span class="original-price">$<?= number_format((float)$item['price'], 2) ?></span>
                                            <span class="final-price">$<?= number_format($item['final_price'], 2) ?></span>
                                            <span class="discount-badge">-<?= $item['discount_applied'] ?>%</span>
                                        <?php else: ?>
                                            <span class="final-price">$<?= number_format($item['final_price'], 2) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <form method="POST" action="" class="cart-item-remove">
                                    <button type="submit" name="remove_game" value="<?= (int)$item['id'] ?>" class="btn-remove">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Cart Summary -->
                    <div class="cart-summary">
                        <h2>Order Summary</h2>
                        
                        <div class="summary-row">
                            <span>Subtotal:</span>
                            <span>$<?= number_format($subtotal, 2) ?></span>
                        </div>

                        <?php if ($total_discount > 0): ?>
                            <div class="summary-row discount">
                                <span>Discount:</span>
                                <span>-$<?= number_format($total_discount, 2) ?></span>
                            </div>
                        <?php endif; ?>

                        <div class="summary-row total">
                            <span>Total:</span>
                            <span>$<?= number_format($total, 2) ?></span>
                        </div>

                        <div class="wallet-info">
                            <p>Wallet Balance: <strong>$<?= number_format($balance, 2) ?></strong></p>
                            <?php if ($balance < $total): ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    Insufficient balance. You need $<?= number_format($total - $balance, 2) ?> more.
                                </div>
                                <a href="pages/user_wallet.php" class="btn btn-secondary">
                                    <i class="fas fa-wallet"></i> Add Funds
                                </a>
                            <?php else: ?>
                                <form method="POST" action="" onsubmit="return confirm('Are you sure you want to purchase these games?');">
                                    <button type="submit" name="confirm_purchase" class="btn btn-primary">
                                        <i class="fas fa-credit-card"></i> Confirm Purchase
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>