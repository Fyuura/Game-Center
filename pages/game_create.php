<?php
require '../functions/auth/session_check.php';
require '../db_config.php';
require '../cloudinary_config.php';

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

if ($role !== 'developer') {
    header('Location: index.php');
    exit;
}

$errors = [];

try {
    $stmt = $pdo->query("
    SELECT *
    FROM categories
    ORDER BY name ASC
    ");
    $fetched_categories = $stmt->fetchAll();
} catch (PDOException $e) {
    $errors[] = "Database error: " . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price_whole = (int) $_POST['price_whole'] ?? 0;
    $price_decimal = (int) $_POST['price_decimal'] ?? 0;
    $price = $price_whole + ($price_decimal / 100);
    $selected_categories = $_POST['categories'] ?? [];
    $min_req = $_POST['requirements']['min'] ?? [];
    $rec_req = $_POST['requirements']['rec'] ?? [];
    $media = isset($_FILES['media']['name']) ? array_filter($_FILES['media']['name'], fn($name) => !empty($name)) : [];

    if (empty($title)) {
        $errors[] = "Title cannot be empty.";
    }

    if (empty($description)) {
        $errors[] = "Description cannot be empty.";
    }

    if ($price_decimal < 0 || $price_decimal > 99) {
        $errors[] = "Decimal part must be between 0 and 99.";
    }

    if ($price < 0) {
        $errors[] = "Price cannot be negative.";
    }

    if (empty($selected_categories)) {
        $errors[] = "Choose at least one category.";
    }

    if (empty($media)) {
        $errors[] = "At least one image or video should be posted.";
    }

    if (!isset($_FILES['cover']) || $_FILES['cover']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "Cover image is required.";
    }

    if (!isset($_FILES['banner']) || $_FILES['banner']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "Banner image is required.";
    }

    foreach ($min_req as $key => $value) {
        if (empty($value)) {
            $errors[] = ucfirst($key) . " should be filled.";
        }
    }

    foreach ($rec_req as $key => $value) {
        if (empty($value)) {
            $errors[] = ucfirst($key) . " should be filled.";
        }
    }

    if (empty($errors)) {
        try {
            // Cloudinary Media Upload
            $uploadedFiles = [];
            $allowedImageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $allowedVideoTypes = ['video/mp4'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);

            // Cover Upload
            $coverMime = finfo_file($finfo, $_FILES['cover']['tmp_name']);
            if (!in_array($coverMime, $allowedImageTypes)) {
                throw new Exception("Cover image must be a valid image file.");
            } else {
                $coverUpload = $cloudinary->uploadApi()->upload($_FILES['cover']['tmp_name'], [
                    'folder' => 'images/game_images/covers'
                ]);
                $uploadedFiles[] = [
                    'cloudinary_public_id' => $coverUpload['public_id'],
                    'cloudinary_url' => $coverUpload['secure_url'],
                    'type' => 'cover'
                ];
            }

            // Banner Upload
            $bannerMime = finfo_file($finfo, $_FILES['banner']['tmp_name']);
            if (!in_array($bannerMime, $allowedImageTypes)) {
                throw new Exception("Banner image must be a valid image file.");
            } else {
                $bannerUpload = $cloudinary->uploadApi()->upload($_FILES['banner']['tmp_name'], [
                    'folder' => 'images/game_images/banners'
                ]);
                $uploadedFiles[] = [
                    'cloudinary_public_id' => $bannerUpload['public_id'],
                    'cloudinary_url' => $bannerUpload['secure_url'],
                    'type' => 'banner'
                ];
            }

            // Preview Upload
            foreach ($_FILES['media']['tmp_name'] as $index => $tmpName) {
                $fileError = $_FILES['media']['error'][$index];
                $fileName = $_FILES['media']['name'][$index];

                if ($fileError === 0) {
                    $mimeType = finfo_file($finfo, $tmpName);

                    if (in_array($mimeType, $allowedImageTypes)) {
                        $type = 'preview_image';
                        $folder = 'images/game_images/preview_images';
                        $resourceType = 'image';
                    } elseif (in_array($mimeType, $allowedVideoTypes)) {
                        $type = 'video';
                        $folder = 'videos';
                        $resourceType = 'video';
                    } else {
                        throw new Exception("$fileName is not a supported image or video file.");
                        continue;
                    }

                    try {
                        $uploadResult = $cloudinary->uploadApi()->upload($tmpName, [
                            'folder' => $folder,
                            'resource_type' => $resourceType
                        ]);

                        $uploadedFiles[] = [
                            'cloudinary_public_id' => $uploadResult['public_id'],
                            'cloudinary_url' => $uploadResult['secure_url'],
                            'type' => $type,
                            'order_index' => $index
                        ];
                    } catch (Exception $e) {
                        $errors[] = "Upload failed for $fileName: " . $e->getMessage();
                    }
                } else {
                    $errors[] = "Error uploading $fileName (Error code: $fileError)";
                }
            }

            finfo_close($finfo);

            $pdo->beginTransaction(); // Transaction başlat

            // Insert into games
            $stmt = $pdo->prepare("
                INSERT INTO games (developer_id, title, description, price)
                VALUES (:developer_id, :title, :description, :price)
            ");
            $stmt->execute([
                ':developer_id' => $user_id,
                ':title' => $title,
                ':description' => $description,
                ':price' => $price
            ]);
            $game_id = $pdo->lastInsertId();

            // Insert requirements
            $stmt = $pdo->prepare("
                INSERT INTO game_requirements (game_id, type, os, processor, memory, graphics, storage, other)
                VALUES (:game_id, :type, :os, :processor, :memory, :graphics, :storage, :other)
            ");

            $stmt->execute([
                ':game_id' => $game_id,
                ':type' => 'minimum',
                ':os' => $min_req['os'] ?? null,
                ':processor' => $min_req['processor'] ?? null,
                ':memory' => $min_req['memory'] ?? null,
                ':graphics' => $min_req['graphics'] ?? null,
                ':storage' => $min_req['storage'] ?? null,
                ':other' => null
            ]);

            $stmt->execute([
                ':game_id' => $game_id,
                ':type' => 'recommended',
                ':os' => $rec_req['os'] ?? null,
                ':processor' => $rec_req['processor'] ?? null,
                ':memory' => $rec_req['memory'] ?? null,
                ':graphics' => $rec_req['graphics'] ?? null,
                ':storage' => $rec_req['storage'] ?? null,
                ':other' => null
            ]);

            foreach ($selected_categories as $cat) {
                $stmt = $pdo->prepare("
                    INSERT INTO game_categories (game_id, category_id)
                    VALUES (:game_id, :category_id)
                ");
                $stmt->execute([
                    ':game_id' => $game_id,
                    ':category_id' => $cat
                ]);
            }

            // Insert media
            foreach ($uploadedFiles as $file) {
                $stmt = $pdo->prepare("
                    INSERT INTO game_media (game_id, type, cloudinary_public_id, cloudinary_url, order_index)
                    VALUES (:game_id, :type, :public_id, :url, :order_index)
                ");
                $stmt->execute([
                    ':game_id' => $game_id,
                    ':type' => $file['type'],
                    ':public_id' => $file['cloudinary_public_id'],
                    ':url' => $file['cloudinary_url'],
                    ':order_index' => $file['order_index'] ?? 0
                ]);
            }

            $pdo->commit(); // Transaction tamam
            $_SESSION['success'] = "Game uploaded successfully! Waiting for admin approval.";
            header('Location: game_create.php');
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            if ($e->getCode() == 23000) { // UNIQUE constraint violation
                $errors[] = "Game with the same title already exists.";
            } else {
                $errors[] = $e->getMessage();
            }
        }
    }
}

// Success mesajını gösterdikten sonra temizle
$success = $_SESSION['success'] ?? '';
unset($_SESSION['success']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Game</title>
    <base href="/gamecenter/">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <link rel="stylesheet" href="assets/css/game_create.css">
</head>

<body>
    <?php include("../components/sidebar.php"); ?>

    <main class="main-content">
        <div class="form-container">
            <h1>Add New Game</h1>

            <?php
            include '../components/alerts.php';
            render_alert($errors, $_SESSION['success'] ?? '');
            ?>
            <form action="" method="POST" enctype="multipart/form-data">

                <!-- Game Title -->
                <div class="form-group">
                    <label for="title">Game Title</label>
                    <input type="text"
                        id="title"
                        name="title"
                        value="<?= htmlspecialchars($title ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        required>
                </div>

                <!-- Description -->
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="8" required><?= htmlspecialchars($description ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>

                <!-- Categories -->
                <div class="form-group">
                    <label>Categories</label>
                    <div class="checkbox-tags">
                        <?php if (empty($fetched_categories)): ?>
                            <i>No categories found.</i>
                        <?php else: ?>
                            <?php foreach ($fetched_categories as $cat): ?>
                                <input type="checkbox"
                                    id="cat-<?= $cat['id'] ?>"
                                    name="categories[]"
                                    value="<?= htmlspecialchars($cat['id']) ?>"
                                    <?= in_array($cat['id'], $selected_categories ?? []) ? 'checked' : '' ?>>
                                <label for="cat-<?= $cat['id'] ?>">
                                    <?= htmlspecialchars($cat['name']) ?>
                                </label>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Price -->
                <div class="form-group">
                    <label for="price_whole">Price (USD)</label>
                    <div class="price-inputs">
                        <input type="number"
                            id="price_whole"
                            name="price_whole"
                            value="<?= htmlspecialchars($price_whole, ENT_QUOTES, 'UTF-8') ?>"
                            min="0"
                            max="10000"
                            required
                            placeholder="0">

                        <span class="dot">.</span>

                        <input type="number"
                            id="price_decimal"
                            name="price_decimal"
                            value="<?= htmlspecialchars($price_decimal, ENT_QUOTES, 'UTF-8') ?>"
                            min="0"
                            max="99"
                            required
                            placeholder="00">
                    </div>
                </div>

                <script src="assets/js/gc_price_check.js"></script>

                <!-- Minimum Requirements -->
                <h3>Minimum Requirements</h3>
                <div class="form-group">
                    <label for="min_os">Operating System</label>
                    <input type="text"
                        id="min_os"
                        name="requirements[min][os]"
                        value="<?= htmlspecialchars($min_req['os'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        required>
                </div>
                <div class="form-group">
                    <label for="min_processor">Processor</label>
                    <input type="text"
                        id="min_processor"
                        name="requirements[min][processor]"
                        value="<?= htmlspecialchars($min_req['processor'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        required>
                </div>
                <div class="form-group">
                    <label for="min_memory">Memory</label>
                    <input type="text"
                        id="min_memory"
                        name="requirements[min][memory]"
                        value="<?= htmlspecialchars($min_req['memory'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        required>
                </div>
                <div class="form-group">
                    <label for="min_graphics">Graphics Card</label>
                    <input type="text"
                        id="min_graphics"
                        name="requirements[min][graphics]"
                        value="<?= htmlspecialchars($min_req['graphics'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        required>
                </div>
                <div class="form-group">
                    <label for="min_storage">Storage</label>
                    <input type="text"
                        id="min_storage"
                        name="requirements[min][storage]"
                        value="<?= htmlspecialchars($min_req['storage'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        required>
                </div>

                <!-- Recommended Requirements -->
                <h3>Recommended Requirements</h3>
                <div class="form-group">
                    <label for="rec_os">Operating System</label>
                    <input type="text"
                        id="rec_os"
                        name="requirements[rec][os]"
                        value="<?= htmlspecialchars($rec_req['os'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        required>
                </div>
                <div class="form-group">
                    <label for="rec_processor">Processor</label>
                    <input type="text"
                        id="rec_processor"
                        name="requirements[rec][processor]"
                        value="<?= htmlspecialchars($rec_req['processor'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        required>
                </div>
                <div class="form-group">
                    <label for="rec_memory">Memory</label>
                    <input type="text"
                        id="rec_memory"
                        name="requirements[rec][memory]"
                        value="<?= htmlspecialchars($rec_req['memory'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        required>
                </div>
                <div class="form-group">
                    <label for="rec_graphics">Graphics Card</label>
                    <input type="text"
                        id="rec_graphics"
                        name="requirements[rec][graphics]"
                        value="<?= htmlspecialchars($rec_req['graphics'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        required>
                </div>
                <div class="form-group">
                    <label for="rec_storage">Storage</label>
                    <input type="text"
                        id="rec_storage"
                        name="requirements[rec][storage]"
                        value="<?= htmlspecialchars($rec_req['storage'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        required>
                </div>

                <!-- Media -->
                <h3>Media</h3>

                <!-- Cover Image -->
                <div class="form-group">
                    <label for="cover">Cover Image</label>
                    <input type="file" id="cover" name="cover" accept="image/*" required>
                </div>

                <!-- Banner Image -->
                <div class="form-group">
                    <label for="banner">Banner Image</label>
                    <input type="file" id="banner" name="banner" accept="image/*" required>
                </div>

                <div class="form-group">
                    <label for="media">Upload Images / Videos</label>
                    <input type="file" id="media" name="media[]" multiple>
                </div>

                <button type="submit" id="submit-btn">Submit Game</button>
                <!-- Prevent rapid submissions -->
                <script>
                    const form = document.querySelector('form');
                    const submitBtn = document.getElementById('submit-btn');

                    form.addEventListener('submit', () => {
                        submitBtn.disabled = true;
                        submitBtn.textContent = 'Uploading... Please wait.';
                    });
                </script>
            </form>
        </div>
    </main>
</body>

</html>