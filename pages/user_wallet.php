<?php
require '../functions/auth/session_check.php';
require '../db_config.php';

$errors = [];
$success_msg = null;
$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    $amount_main = (int)($_POST['amount_main'] ?? 0);
    $amount_decimal = (int)($_POST['amount_decimal'] ?? 0);

    $full_amount_string = $amount_main . '.' . $amount_decimal;
    $amount = (float)$full_amount_string;

    try {
        switch ($action) {
            case 'add':
                if ($amount > 0) {
                    $stmt = $pdo->prepare("
                    UPDATE users 
                    SET balance = balance + :amount 
                    WHERE id = :id
                    ");

                    $stmt->execute([
                        'amount' => $amount,
                        'id' => $user_id
                    ]);
                    $success_msg = number_format($amount, 2, ',', '.') . "$ has been added to your balance.";
                } else {
                    $errors[] = "Please enter a positive amount.";
                }
                break;

            case 'set':
                if ($amount >= 0) {
                    $stmt = $pdo->prepare("
                    UPDATE users 
                    SET balance = :amount 
                    WHERE id = :id
                    ");

                    $stmt->execute([
                        'amount' => $amount,
                        'id' => $user_id
                    ]);

                    $success_msg = "Balance has been set as " . number_format($amount, 2, ',', '.') . "$.";
                } else {
                    $errors[] = "Please enter a non-negative amount.";
                }
                break;

            case 'reset':
                $stmt = $pdo->prepare("
                UPDATE users 
                SET balance = 0 
                WHERE id = :id
                ");

                $stmt->execute([
                    'id' => $user_id
                ]);

                $success_msg = "Balance has been reset.";
                break;

            default:
                $errors[] = "Geçersiz işlem.";
        }
    } catch (PDOException $e) {
        $errors[] = "Veritabanı hatası: " . $e->getMessage();
    }
    
    header("Location: user_wallet.php");
    exit;
}

// 2. Güncel Bakiyeyi Çek
try {
    $stmt = $pdo->prepare("
    SELECT balance 
    FROM users 
    WHERE id = :user_id
    ");

    $stmt->execute([
        'user_id' => $user_id
    ]);

    $user = $stmt->fetch();
} catch (PDOException $e) {
    $errors[] = "Database error: " . $e->getMessage();
    $user['balance'] = 0;
}


$balance_parts = explode('.', number_format($user['balance'], 2, '.', ''));
$balance_main = $balance_parts[0];
$balance_decimal = $balance_parts[1];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Balance</title>
    <base href="/gamecenter/">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <link rel="stylesheet" href="assets/css/cart_btn.css">
    <link rel="stylesheet" href="assets/css/balance_btn.css">
    <link rel="stylesheet" href="assets/css/user_wallet.css">
    <link rel="stylesheet" href="assets/css/alerts.css">
</head>

<body>
    <?php include("../components/sidebar.php"); ?>

    <?php include("../components/cart_btn.php");
    renderCartButton(); ?>

    <?php include("../components/balance_btn.php");
    renderBalanceButton($user['balance']); ?>

    <main class="main-content">


        <div class="form-container">

            <h1>Manage Balance</h1>

            <?php include('../components/alerts.php');
            render_alert($errors, $success_msg);
            ?>
            <div class="current-balance-box">
                <div style="color: #bbb; margin-bottom: 5px;">Balance</div>
                <span class="balance-value"><?php echo $balance_main; ?></span>
                <span class="balance-currency">.<?php echo $balance_decimal; ?> ₺</span>
            </div>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="amount">Enter an Amount</label>

                    <div class="price-inputs">
                        <input type="number" name="amount_main" class="main-amount" placeholder="00" min="0" required>
                        <span class="dot">.</span>
                        <input type="number" name="amount_decimal" class="decimal-amount" placeholder="00" min="0" max="99" oninput="if(this.value.length > 2) this.value = this.value.slice(0, 2);">
                        <span style="color:white; font-weight:bold; margin-left:5px;">TL</span>
                    </div>
                </div>

                <div class="btn-group">
                    <button type="submit" name="action" value="add">Add Balance</button>
                    <button type="submit" name="action" value="set" class="btn-set">Set Balance</button>
                </div>
            </form>

            <hr style="border-color: rgb(60,65,85); margin: 30px 0;">

            <form method="POST" action="">
                <div class="form-group">
                    <button type="submit" name="action" value="reset" class="btn-reset"> Reset Balance </button>
                </div>
            </form>
        </div>

    </main>
</body>

</html>