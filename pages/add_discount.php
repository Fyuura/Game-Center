<?php
require '../db_config.php';
require '../functions/auth/session_check.php';

// Ensure session is available
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Get developer id from session
$developer_id = null;
if (isset($_SESSION['user']['id'])) {
    $developer_id = (int)$_SESSION['user']['id'];
} elseif (isset($_SESSION['user_id'])) {
    $developer_id = (int)$_SESSION['user_id'];
} elseif (isset($_SESSION['id'])) {
    $developer_id = (int)$_SESSION['id'];
}

if (!$developer_id) {
    header("Location: /gamecenter/login.php");
    exit;
}

// Get game id from query parameter
$game_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (!$game_id) {
    header("Location: /gamecenter/pages/dev_dashboard.php");
    exit;
}

// Verify that the game belongs to this developer and is approved
$stmt = $pdo->prepare("SELECT id, title, status FROM games WHERE id = ? AND developer_id = ? AND status = 'approved'");
$stmt->execute([$game_id, $developer_id]);
$game = $stmt->fetch();

if (!$game) {
    // Game not found, doesn't belong to developer, or is not approved
    header("Location: /gamecenter/pages/dev_dashboard.php");
    exit;
}

// Check if a discount already exists for this game
$stmt = $pdo->prepare("SELECT discount_pct, start_date, end_date FROM game_discounts WHERE game_id = ?");
$stmt->execute([$game_id]);
$existing_discount = $stmt->fetch();

$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $discount_pct = isset($_POST['discount_pct']) ? (int)$_POST['discount_pct'] : null;
    $start_date = isset($_POST['start_date']) ? $_POST['start_date'] : null;
    $end_date = isset($_POST['end_date']) ? $_POST['end_date'] : null;

    // Validation
    if (!$discount_pct || $discount_pct < 5 || $discount_pct > 100) {
        $error = 'Discount percentage must be between 5 and 100.';
    } elseif (!$start_date || !$end_date) {
        $error = 'Start date and end date are required.';
    } elseif (strtotime($start_date) >= strtotime($end_date)) {
        $error = 'End date must be after start date.';
    } else {
        try {
            if ($existing_discount) {
                // Update existing discount
                $stmt = $pdo->prepare("
                    UPDATE game_discounts 
                    SET discount_pct = ?, start_date = ?, end_date = ? 
                    WHERE game_id = ?
                ");
                $stmt->execute([$discount_pct, $start_date, $end_date, $game_id]);
                $message = 'Discount updated successfully!';
            } else {
                // Insert new discount
                $stmt = $pdo->prepare("
                    INSERT INTO game_discounts (game_id, discount_pct, start_date, end_date) 
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$game_id, $discount_pct, $start_date, $end_date]);
                $message = 'Discount added successfully!';
            }

            // Update game status to pending for admin review
            $stmt = $pdo->prepare("UPDATE games SET status = 'pending' WHERE id = ?");
            $stmt->execute([$game_id]);

            // Refresh existing discount data
            // Refresh game info
            $stmt = $pdo->prepare("SELECT id, title, status FROM games WHERE id = ?");
            $stmt->execute([$game_id]);
            $game = $stmt->fetch();
 
             // Refresh discount data
             $stmt = $pdo->prepare("SELECT discount_pct, start_date, end_date FROM game_discounts WHERE game_id = ?");
             $stmt->execute([$game_id]);
             $existing_discount = $stmt->fetch();
        } catch (Exception $e) {
            $error = 'An error occurred: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Discount - <?= htmlspecialchars($game['title']) ?></title>
    <base href="/gamecenter/">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <link rel="stylesheet" href="assets/css/add_discount.css">
</head>

<body>

    <?php include("../components/sidebar.php"); ?>

    <main class="main-content">

        <section class="discount-section">
            <div class="discount-form">

                <h2>
                    <i class="fas fa-tag"></i> Add Discount
                </h2>

                <p class="game-title">
                    Game: <strong><?= htmlspecialchars($game['title']) ?></strong>
                </p>

                <?php if ($message): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <?php if ($existing_discount): ?>
                    <div class="current-discount">
                        <h4><i class="fas fa-info-circle"></i> Current Discount</h4>
                        <p><strong>Discount:</strong> <?= (int)$existing_discount['discount_pct'] ?>%</p>
                        <p><strong>Start Date:</strong> <?= htmlspecialchars($existing_discount['start_date']) ?></p>
                        <p><strong>End Date:</strong> <?= htmlspecialchars($existing_discount['end_date']) ?></p>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">

                    <div class="form-group">
                        <label for="discount_pct">Discount Percentage (%)</label>
                        <input 
                            type="number" 
                            id="discount_pct" 
                            name="discount_pct" 
                            min="5" 
                            max="100" 
                            step="1"
                            value="<?= $existing_discount ? (int)$existing_discount['discount_pct'] : '' ?>"
                            required
                        >
                        <div class="hint">Enter a value between 5 and 100</div>
                    </div>

                    <div class="form-group">
                        <label for="start_date">Start Date</label>
                        <input 
                            type="date" 
                            id="start_date" 
                            name="start_date" 
                            value="<?= $existing_discount ? htmlspecialchars($existing_discount['start_date']) : date('Y-m-d') ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="end_date">End Date</label>
                        <input 
                            type="date" 
                            id="end_date" 
                            name="end_date" 
                            value="<?= $existing_discount ? htmlspecialchars($existing_discount['end_date']) : '' ?>"
                            required
                        >
                    </div>

                    <div class="form-buttons">
                        <button type="submit" class="btn btn-submit">
                            <i class="fas fa-save"></i>
                            <?= $existing_discount ? 'Update Discount' : 'Add Discount' ?>
                        </button>
                        <a href="/gamecenter/pages/dev_dashboard.php" class="btn btn-back">
                            <i class="fas fa-arrow-left"></i> Back
                        </a>
                    </div>

                </form>

            </div>
        </section>

    </main>

</body>
</html>