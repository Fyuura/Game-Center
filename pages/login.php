<?php
require '../db_config.php';
require '../components/alerts.php';

session_start();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($identifier === '') {
        $errors[] = "Please enter your username or email.";
    }

    if ($password === '') {
        $errors[] = "Password cannot be empty.";
    } else if (strlen($password) < 6) {
        $errors[] = "Password should be at least 6 characters long.";
    }

    if (empty($errors)) {
        try {
            if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
                $stmt = $pdo->prepare("
                    SELECT *
                    FROM users
                    WHERE email = :identifier
                ");
            } else {
                $stmt = $pdo->prepare("
                    SELECT *
                    FROM users
                    WHERE username = :identifier
                ");
            }

            $stmt->execute([
                ':identifier' => $identifier
            ]);

            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];
                header('Location: ../index.php');
                exit;
            } else {
                $errors[] = "Incorrect username/email or password.";
            }
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <base href="/gamecenter/">
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/auth.css">
</head>

<body>
    <main>
        <h1>Login</h1>
        <?php 
        render_alert($errors);
        ?>

        <form action="" method="post">
            <!-- Username or Email -->
            <div>
                <label for="login-identifier">E-mail or Username</label>
                <input
                    type="text"
                    id="login-identifier"
                    name="identifier"
                    inputmode="email"
                    autocomplete="username"
                    required
                    placeholder="example@site.com or username" />
            </div>

            <!-- Password -->
            <div>
                <label for="login-password">Password</label>
                <input
                    type="password"
                    id="login-password"
                    name="password"
                    autocomplete="current-password"
                    required
                    minlength="6"
                    placeholder="••••••••" />
            </div>

            <button type="submit">Login</button>
        </form>

        <p>Don't have an account? <a href="pages/register.php">Register</a></p>
    </main>
</body>

</html>