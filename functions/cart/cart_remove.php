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
    $user_id = $_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? $_SESSION['id'] ?? null;
    
    if (!$user_id) {
        $response['message'] = 'Not authenticated.';
        echo json_encode($response);
        exit;
    }

    $game_id = isset($_POST['game_id']) ? (int)$_POST['game_id'] : null;

    if (!$game_id) {
        $response['message'] = 'Invalid game ID.';
        echo json_encode($response);
        exit;
    }

    // Remove from cart
    $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ? AND game_id = ?");
    $stmt->execute([$user_id, $game_id]);

    $response['success'] = true;
    $response['message'] = 'Item removed from cart.';

} catch (PDOException $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($response);
?>