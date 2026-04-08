<?php
require '../db_config.php';
require '../components/alerts.php';

session_start();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirmation'] ?? '';

    if ($username === '') {
        $errors[] = "Username cannot be empty.";
    } else if (strlen($username) < 3) {
        $errors[] = "Username should be at least 3 characters long.";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email.";
    }

    if ($password === '') {
        $errors[] = "Password cannot be empty.";
    } else if (strlen($password) < 6) {
        $errors[] = "Password should be at least 6 characters long";
    } else if ($password !== $password_confirm) {
        $errors[] = "Passwords do not match";
    }

    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        try {
            $stmt = $pdo->prepare("
                INSERT INTO users (email, username, password)
                VALUES (:email, :username, :password)
            ");

            $stmt->execute([
                ':email' => $email,
                ':username' => $username,
                ':password' => $hashed_password
            ]);

            $user_id = $pdo->lastInsertId();
            $stmt = $pdo->prepare("
                SELECT role 
                FROM users 
                WHERE id = :id
            ");
            $stmt->execute([
                ':id' => $user_id
            ]);
            $user = $stmt->fetch();

            $_SESSION['user_id'] = $user_id;
            $_SESSION['role'] = $user['role'];
            header('Location: ../index.php');
            exit;
        } catch (PDOException $e) {
            if ($e->errorInfo[1] == 1062) {
                // 1062 = duplicate entry
                if (str_contains($e->getMessage(), 'username')) {
                    $errors[] = "This username is already taken.";
                } elseif (str_contains($e->getMessage(), 'email')) {
                    $errors[] = "This email is already registered.";
                } else {
                    $errors[] = "Duplicate entry.";
                }
            } else {
                $errors[] = "Database error: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register</title>
    <base href="/gamecenter/">
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/auth.css">
</head>

<body>
    <main>
        <h1>Register</h1>

        <?php
        render_alert($errors);
        ?>

        <form action="" method="post">
            <!-- Username -->
            <div>
                <label for="reg-username">Username</label>
                <input
                    type="text"
                    id="reg-username"
                    name="username"
                    required
                    minlength="3"
                    maxlength="32"
                    placeholder="Username" />
            </div>

            <!-- Email -->
            <div>
                <label for="reg-email">E-mail</label>
                <input
                    type="email"
                    id="reg-email"
                    name="email"
                    required
                    placeholder="example@site.com" />
            </div>

            <!-- Password -->
            <div>
                <label for="reg-password">Password</label>
                <input
                    type="password"
                    id="reg-password"
                    name="password"
                    required
                    minlength="6"
                    placeholder="••••••••" />
            </div>

            <!-- Confirm Password -->
            <div>
                <label for="reg-password2">Confirm Password</label>
                <input
                    type="password"
                    id="reg-password2"
                    name="password_confirmation"
                    required
                    minlength="6"
                    placeholder="••••••••" />
            </div>

            <button type="submit">Register</button>
        </form>

        <p>Already have an account? <a href="pages/login.php">Login</a></p>
    </main>
</body>

</html>