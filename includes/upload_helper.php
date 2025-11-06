<?php
// includes/upload_helper.php

if (!function_exists('gg_secure_upload')) {
    /**
     * Validate and store an uploaded file using safe defaults.
     *
     * @param array  $file        The $_FILES[...] entry.
     * @param string $destination Destination directory (absolute path preferred).
     * @param array|null $allowedMimes List of allowed MIME types.
     * @param int|null   $maxBytes Maximum allowed file size in bytes.
     *
     * @return array{success:bool, filename:?string, message:?string}
     */
    function gg_secure_upload(array $file, string $destination, ?array $allowedMimes = null, ?int $maxBytes = null): array
    {
        $allowedMimes = $allowedMimes ?? [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp'
        ];
        $maxBytes = $maxBytes ?? (5 * 1024 * 1024); // 5 MB default

        if (!isset($file['error']) || is_array($file['error'])) {
            return ['success' => false, 'filename' => null, 'message' => 'Invalid file upload parameters.'];
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE   => 'The uploaded file exceeds the server limit.',
                UPLOAD_ERR_FORM_SIZE  => 'The uploaded file exceeds the permitted size.',
                UPLOAD_ERR_PARTIAL    => 'The file was only partially uploaded.',
                UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder on the server.',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
                UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the file upload.'
            ];
            $msg = $errorMessages[$file['error']] ?? 'An unknown upload error occurred.';
            return ['success' => false, 'filename' => null, 'message' => $msg];
        }

        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['success' => false, 'filename' => null, 'message' => 'Upload validation failed.'];
        }

        if (($file['size'] ?? 0) > $maxBytes) {
            return ['success' => false, 'filename' => null, 'message' => 'File is too large. Maximum allowed size is ' . number_format($maxBytes / (1024 * 1024), 2) . ' MB.'];
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']) ?: '';
        if (!in_array($mime, $allowedMimes, true)) {
            return ['success' => false, 'filename' => null, 'message' => 'Unsupported file type uploaded.'];
        }

        $extensionMap = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf'
        ];
        $extension = $extensionMap[$mime] ?? strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION) ?: 'bin');
        $safeName  = time() . '_' . bin2hex(random_bytes(6)) . '.' . $extension;

        $destination = rtrim($destination, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (!is_dir($destination) && !mkdir($destination, 0775, true)) {
            return ['success' => false, 'filename' => null, 'message' => 'Failed to prepare upload directory.'];
        }
        if (!is_writable($destination)) {
            return ['success' => false, 'filename' => null, 'message' => 'Upload directory is not writable.'];
        }

        $targetPath = $destination . $safeName;
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            return ['success' => false, 'filename' => null, 'message' => 'Failed to store uploaded file.'];
        }

        return ['success' => true, 'filename' => $safeName, 'message' => null];
    }
}
