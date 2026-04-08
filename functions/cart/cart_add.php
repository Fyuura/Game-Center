<?php
require '../../db_config.php';
require '../auth/session_check.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$response = [
    'success' => false,
    'message' => ''
];

try {
    // Check if user is authenticated
    $user_id = $_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? $_SESSION['id'] ?? null;
    
    if (!$user_id) {
        $response['message'] = 'Please log in to add items to cart.';
        echo json_encode($response);
        exit;
    }

    // Get game id from request
    $game_id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['game_id']) ? (int)$_POST['game_id'] : null);

    if (!$game_id) {
        $response['message'] = 'Invalid game ID.';
        echo json_encode($response);
        exit;
    }

    // Verify game exists and is approved
    $stmt = $pdo->prepare("SELECT id, title FROM games WHERE id = ? AND status = 'approved'");
    $stmt->execute([$game_id]);
    $game = $stmt->fetch();

    if (!$game) {
        $response['message'] = 'Game not found or not available.';
        echo json_encode($response);
        exit;
    }

    // Check if already in cart
    $stmt = $pdo->prepare("SELECT id FROM cart WHERE user_id = ? AND game_id = ?");
    $stmt->execute([$user_id, $game_id]);
    $existing = $stmt->fetch();

    if ($existing) {
        $response['message'] = 'Game is already in your cart.';
        echo json_encode($response);
        exit;
    }

    // Check if user already owns this game
    $stmt = $pdo->prepare("SELECT id FROM user_games WHERE user_id = ? AND game_id = ?");
    $stmt->execute([$user_id, $game_id]);
    $owned = $stmt->fetch();

    if ($owned) {
        $response['message'] = 'You already own this game.';
        echo json_encode($response);
        exit;
    }

    // Add to cart
    $stmt = $pdo->prepare("INSERT INTO cart (user_id, game_id) VALUES (?, ?)");
    $stmt->execute([$user_id, $game_id]);

    $response['success'] = true;
    $response['message'] = 'Game added to cart successfully!';

} catch (PDOException $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($response);
exit;
?>