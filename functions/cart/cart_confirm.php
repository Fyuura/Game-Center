<?php
require '../../db_config.php';
require '../auth/session_check.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$response = [
    'success' => false,
    'message' => '',
    'total' => 0
];

try {
    $user_id = $_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? $_SESSION['id'] ?? null;
    
    if (!$user_id) {
        $response['message'] = 'Not authenticated.';
        echo json_encode($response);
        exit;
    }

    // Get user's current balance
    $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        $response['message'] = 'User not found.';
        echo json_encode($response);
        exit;
    }

    $balance = (float)$user['balance'];

    // Get all cart items with active discounts
    $stmt = $pdo->prepare("
        SELECT C.id as cart_id, G.id, G.title, G.price, 
               GD.discount_pct, GD.start_date, GD.end_date
        FROM cart C
        JOIN games G ON C.game_id = G.id
        LEFT JOIN game_discounts GD ON G.id = GD.game_id
        WHERE C.user_id = ?
        FOR UPDATE
    ");
    $stmt->execute([$user_id]);
    $cart_items = $stmt->fetchAll();

    if (empty($cart_items)) {
        $response['message'] = 'Your cart is empty.';
        echo json_encode($response);
        exit;
    }

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
        $response['message'] = 'Insufficient balance. Please add funds to your wallet.';
        $response['required'] = $total - $balance;
        echo json_encode($response);
        exit;
    }

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

        $response['success'] = true;
        $response['message'] = 'Purchase successful! ' . count($purchased_games) . ' game(s) added to your library.';
        $response['total'] = $total;
        $response['games'] = $purchased_games;

    } catch (PDOException $e) {
        $pdo->rollBack();
        
        // Handle duplicate entry error
        if ($e->getCode() == 23000) {
            $response['message'] = 'You already own one or more games in your cart. Please remove duplicates and try again.';
        } else {
            throw $e;
        }
    }

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $response['message'] = 'Database error: ' . $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($response);
?>