<?php
require_once 'includes/db.php';

header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    if ($method === 'GET') {
        if ($action === 'get_profile') {
            $stmt = $pdo->prepare("SELECT username, email, nome, cognome, profile_image FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Add full URL for image if exists
            if ($user['profile_image']) {
                $user['profile_image_url'] = 'uploads/avatars/' . $user['profile_image'] . '?t=' . time(); // Cache busting
            } else {
                $user['profile_image_url'] = null;
            }

            echo json_encode(['success' => true, 'data' => $user]);
        }
    } elseif ($method === 'POST') {
        if ($action === 'update_profile') {
            $nome = $_POST['nome'] ?? '';
            $cognome = $_POST['cognome'] ?? '';
            $email = $_POST['email'] ?? '';
            $password = $_POST['password'] ?? '';

            $pdo->beginTransaction();

            $sql = "UPDATE users SET nome = ?, cognome = ?, email = ? WHERE id = ?";
            $params = [$nome, $cognome, $email, $userId];

            if (!empty($password)) {
                $sql = "UPDATE users SET nome = ?, cognome = ?, email = ?, password = ? WHERE id = ?";
                $params = [$nome, $cognome, $email, password_hash($password, PASSWORD_DEFAULT), $userId];
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            // Handle Image Upload
            if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['avatar'];
                $allowed = ['image/jpeg', 'image/png', 'image/webp'];

                if (!in_array($file['type'], $allowed)) {
                    throw new Exception("Formato file non supportato. Usa JPG, PNG o WebP.");
                }

                if ($file['size'] > 5 * 1024 * 1024) { // 5MB limit hard cap, but we resize anyway
                    throw new Exception("File troppo grande. Max 5MB.");
                }

                $uploadDir = __DIR__ . '/uploads/avatars/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                // Create image resource
                $srcImage = null;
                switch ($file['type']) {
                    case 'image/jpeg':
                        $srcImage = imagecreatefromjpeg($file['tmp_name']);
                        break;
                    case 'image/png':
                        $srcImage = imagecreatefrompng($file['tmp_name']);
                        break;
                    case 'image/webp':
                        $srcImage = imagecreatefromwebp($file['tmp_name']);
                        break;
                }

                if (!$srcImage) {
                    throw new Exception("Errore processamento immagine.");
                }

                // Resize to 300x300 fit
                $width = imagesx($srcImage);
                $height = imagesy($srcImage);
                $newWidth = 300;
                $newHeight = 300;

                // Crop center or fit? Let's just resize/stretch for simple square avatar or maintain aspect ratio if careful.
                // Simple approach: Resize preserving aspect ratio to fit 300x300, then crop or simple resize.
                // Request implies "profile image", usually square. Let's simple resize to 300x300 (might distort) or crop.
                // Better UX: Crop to square from center.

                // Calculate crop
                $aspect = $width / $height;
                if ($aspect >= 1) { // Landscape
                    $cropW = $height;
                    $cropH = $height;
                    $cropX = ($width - $height) / 2;
                    $cropY = 0;
                } else { // Portrait
                    $cropW = $width;
                    $cropH = $width;
                    $cropX = 0;
                    $cropY = ($height - $width) / 2;
                }

                $dstImage = imagecreatetruecolor(300, 300);
                imagecopyresampled($dstImage, $srcImage, 0, 0, $cropX, $cropY, 300, 300, $cropW, $cropH);

                // Save as JPG - Overwrite based on User ID
                $fileName = 'user_' . $userId . '.jpg';
                $destination = $uploadDir . $fileName;

                imagejpeg($dstImage, $destination, 80); // 80% quality

                imagedestroy($srcImage);
                imagedestroy($dstImage);

                // Update DB with filename
                $stmt = $pdo->prepare("UPDATE users SET profile_image = ? WHERE id = ?");
                $stmt->execute([$fileName, $userId]);
            }

            $pdo->commit();
            echo json_encode(['success' => true]);
        }
    }

} catch (Exception $e) {
    if ($pdo->inTransaction())
        $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>