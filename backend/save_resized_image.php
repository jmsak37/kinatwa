<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/schema_sync.php';

header('Content-Type: application/json');

requireLogin();

try {
    syncKinatwaSchema($pdo);

    $fileName = trim($_POST['resize_file_name'] ?? '');
    $widthScale = max(0.05, (float)($_POST['width_scale'] ?? 1));
    $heightScale = max(0.05, (float)($_POST['height_scale'] ?? 1));
    $moveX = (int)($_POST['move_x'] ?? 0);
    $moveY = (int)($_POST['move_y'] ?? 0);

    if ($fileName === '') {
        echo json_encode([
            "ok" => false,
            "message" => "Image file name is required."
        ]);
        exit;
    }

    $baseDir = realpath(__DIR__ . '/../uploads/KINAT');
    if (!$baseDir || !is_dir($baseDir)) {
        echo json_encode([
            "ok" => false,
            "message" => "Upload folder not found."
        ]);
        exit;
    }

    $relativeFile = ltrim(str_replace(['\\', '..'], ['/', ''], $fileName), '/');
    $target = $baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeFile);

    if (!is_file($target)) {
        echo json_encode([
            "ok" => false,
            "message" => "Image file not found."
        ]);
        exit;
    }

    $realTarget = realpath($target);
    if (!$realTarget || strpos($realTarget, $baseDir) !== 0) {
        echo json_encode([
            "ok" => false,
            "message" => "Invalid image path."
        ]);
        exit;
    }

    $ext = '.' . strtolower(pathinfo($realTarget, PATHINFO_EXTENSION));
    $allowed = ['.png', '.jpg', '.jpeg', '.webp', '.gif', '.bmp'];

    if (!in_array($ext, $allowed, true)) {
        echo json_encode([
            "ok" => false,
            "message" => "Only image files can be resized."
        ]);
        exit;
    }

    $imageInfo = @getimagesize($realTarget);
    if (!$imageInfo) {
        echo json_encode([
            "ok" => false,
            "message" => "Could not read the image."
        ]);
        exit;
    }

    $origW = (int)$imageInfo[0];
    $origH = (int)$imageInfo[1];

    if ($origW <= 0 || $origH <= 0) {
        echo json_encode([
            "ok" => false,
            "message" => "Invalid image size."
        ]);
        exit;
    }

    $newW = max(1, (int)round($origW * $widthScale));
    $newH = max(1, (int)round($origH * $heightScale));

    $dstX = (int)round((($origW - $newW) / 2) + $moveX);
    $dstY = (int)round((($origH - $newH) / 2) + $moveY);

    $done = false;
    $errors = [];

    if (function_exists('imagecreatetruecolor')) {
        $src = null;

        if ($ext === '.png' && function_exists('imagecreatefrompng')) {
            $src = @imagecreatefrompng($realTarget);
        } elseif (($ext === '.jpg' || $ext === '.jpeg') && function_exists('imagecreatefromjpeg')) {
            $src = @imagecreatefromjpeg($realTarget);
        } elseif ($ext === '.webp' && function_exists('imagecreatefromwebp')) {
            $src = @imagecreatefromwebp($realTarget);
        } elseif ($ext === '.gif' && function_exists('imagecreatefromgif')) {
            $src = @imagecreatefromgif($realTarget);
        } elseif ($ext === '.bmp' && function_exists('imagecreatefrombmp')) {
            $src = @imagecreatefrombmp($realTarget);
        }

        if ($src !== false && $src !== null) {
            $canvas = imagecreatetruecolor($origW, $origH);

            if ($canvas !== false) {
                $supportsTransparency = in_array($ext, ['.png', '.webp', '.gif'], true);

                if ($supportsTransparency) {
                    imagealphablending($canvas, false);
                    imagesavealpha($canvas, true);
                    $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
                    imagefilledrectangle($canvas, 0, 0, $origW, $origH, $transparent);
                } else {
                    $white = imagecolorallocate($canvas, 255, 255, 255);
                    imagefilledrectangle($canvas, 0, 0, $origW, $origH, $white);
                }

                imagecopyresampled($canvas, $src, $dstX, $dstY, 0, 0, $newW, $newH, $origW, $origH);

                $saved = false;
                if ($ext === '.png') {
                    $saved = @imagepng($canvas, $realTarget);
                } elseif ($ext === '.jpg' || $ext === '.jpeg') {
                    $saved = @imagejpeg($canvas, $realTarget, 92);
                } elseif ($ext === '.webp' && function_exists('imagewebp')) {
                    $saved = @imagewebp($canvas, $realTarget, 92);
                } elseif ($ext === '.gif') {
                    $saved = @imagegif($canvas, $realTarget);
                } elseif ($ext === '.bmp' && function_exists('imagebmp')) {
                    $saved = @imagebmp($canvas, $realTarget);
                }

                imagedestroy($src);
                imagedestroy($canvas);

                if ($saved) {
                    $done = true;
                } else {
                    $errors[] = 'GD could not save the resized image.';
                }
            } else {
                if (is_resource($src) || (is_object($src) && get_resource_type($src) !== 'Unknown')) {
                    @imagedestroy($src);
                }
                $errors[] = 'GD could not create the canvas.';
            }
        } else {
            $errors[] = 'GD is enabled but this image format handler is not available.';
        }
    } else {
        $errors[] = 'GD is not enabled.';
    }

    if (!$done && class_exists('Imagick')) {
        try {
            $img = new Imagick($realTarget);
            $supportsTransparency = in_array($ext, ['.png', '.webp', '.gif'], true);

            $img->resizeImage($newW, $newH, Imagick::FILTER_LANCZOS, 1, true);

            $canvas = new Imagick();
            $canvas->newImage(
                $origW,
                $origH,
                $supportsTransparency ? new ImagickPixel('transparent') : new ImagickPixel('white')
            );

            $canvas->setImageFormat(ltrim($ext, '.'));
            $canvas->compositeImage($img, Imagick::COMPOSITE_DEFAULT, $dstX, $dstY);

            if ($ext === '.jpg' || $ext === '.jpeg') {
                $canvas->setImageCompressionQuality(92);
                $canvas->setImageFormat('jpeg');
            } elseif ($ext === '.png') {
                $canvas->setImageFormat('png');
            } elseif ($ext === '.webp') {
                $canvas->setImageFormat('webp');
            } elseif ($ext === '.gif') {
                $canvas->setImageFormat('gif');
            } elseif ($ext === '.bmp') {
                $canvas->setImageFormat('bmp');
            }

            $canvas->writeImage($realTarget);

            $img->clear();
            $img->destroy();
            $canvas->clear();
            $canvas->destroy();

            $done = true;
        } catch (Throwable $imagickError) {
            $errors[] = 'Imagick failed: ' . $imagickError->getMessage();
        }
    } elseif (!$done) {
        $errors[] = 'Imagick is not installed.';
    }

    if (!$done) {
        echo json_encode([
            "ok" => false,
            "message" => "Image resize failed on this server. Enable GD in XAMPP PHP with the needed image handlers, or install Imagick.",
            "details" => $errors
        ]);
        exit;
    }

    $metaStmt = $pdo->prepare("
        SELECT id
        FROM media_files
        WHERE file_name = ?
        LIMIT 1
    ");
    $metaStmt->execute([$relativeFile]);
    $fileRow = $metaStmt->fetch(PDO::FETCH_ASSOC);

    if ($fileRow) {
        $upd = $pdo->prepare("
            UPDATE media_files
            SET file_path = ?
            WHERE id = ?
        ");
        $upd->execute([$realTarget, $fileRow['id']]);
    }

    $pdo->prepare("
        UPDATE display_state
        SET version = version + 1
        WHERE id = 1
    ")->execute();

    $pdo->prepare("
        INSERT INTO notifications (title, message, target_role, link_url)
        VALUES ('Image Edited', ?, 'all', '')
    ")->execute(["Edited image saved successfully: {$relativeFile}"]);

    echo json_encode([
        "ok" => true,
        "message" => "Edited image saved successfully."
    ]);
} catch (Throwable $e) {
    echo json_encode([
        "ok" => false,
        "message" => $e->getMessage()
    ]);
}