<?php

declare(strict_types=1);

const MAX_UPLOAD_BYTES = 52_428_800;
const MAX_UNCOMPRESSED_BYTES = 209_715_200;
const MAX_ZIP_ENTRIES = 5_000;

header('Content-Type: application/json; charset=utf-8');

function respond(int $status, array $data): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function bearerToken(): string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/^Bearer\s+(.+)$/i', trim($header), $matches)) {
        return '';
    }

    return trim($matches[1]);
}

function validZip(string $path): array
{
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        return [false, 'Paket bukan ZIP yang valid.'];
    }

    if ($zip->numFiles < 1 || $zip->numFiles > MAX_ZIP_ENTRIES) {
        $zip->close();
        return [false, 'Jumlah file dalam ZIP tidak diizinkan.'];
    }

    $totalSize = 0;

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $stat = $zip->statIndex($i);
        if ($stat === false) {
            $zip->close();
            return [false, 'Metadata ZIP tidak dapat dibaca.'];
        }

        $name = str_replace('\\', '/', (string) $stat['name']);
        $parts = explode('/', $name);

        if (
            $name === ''
            || str_contains($name, "\0")
            || str_starts_with($name, '/')
            || preg_match('/^[A-Za-z]:\//', $name)
            || in_array('..', $parts, true)
        ) {
            $zip->close();
            return [false, 'ZIP memuat path yang tidak aman.'];
        }

        $totalSize += (int) ($stat['size'] ?? 0);
        if ($totalSize > MAX_UNCOMPRESSED_BYTES) {
            $zip->close();
            return [false, 'Ukuran hasil ekstraksi terlalu besar.'];
        }

        $opsys = 0;
        $attributes = 0;
        if ($zip->getExternalAttributesIndex($i, $opsys, $attributes)) {
            $fileType = ($attributes >> 16) & 0170000;
            if ($fileType === 0120000) {
                $zip->close();
                return [false, 'Symbolic link tidak diizinkan dalam ZIP.'];
            }
        }
    }

    $zip->close();
    return [true, 'ok'];
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    respond(405, ['ok' => false, 'message' => 'Gunakan metode POST.']);
}

$tokenFile = '/etc/autodeploy/token';
$expectedToken = is_readable($tokenFile) ? trim((string) file_get_contents($tokenFile)) : '';
$providedToken = bearerToken();

if ($expectedToken === '' || $providedToken === '' || !hash_equals($expectedToken, $providedToken)) {
    respond(401, ['ok' => false, 'message' => 'Token deployment tidak valid.']);
}

$contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength <= 0 || $contentLength > MAX_UPLOAD_BYTES) {
    respond(413, ['ok' => false, 'message' => 'Ukuran request tidak diizinkan (maksimum 50 MB).']);
}

$project = strtolower(trim((string) ($_POST['project'] ?? '')));
$branch = trim((string) ($_POST['branch'] ?? 'main'));
$commit = strtolower(trim((string) ($_POST['commit'] ?? 'unknown')));

if (!preg_match('/^[a-z0-9][a-z0-9-]{1,38}[a-z0-9]$/', $project)) {
    respond(422, ['ok' => false, 'message' => 'Nama proyek harus 3–40 karakter: huruf kecil, angka, atau tanda hubung.']);
}

if (!preg_match('#^[A-Za-z0-9._/-]{1,100}$#', $branch)) {
    respond(422, ['ok' => false, 'message' => 'Nama branch tidak valid.']);
}

if ($commit !== 'unknown' && !preg_match('/^[a-f0-9]{7,64}$/', $commit)) {
    respond(422, ['ok' => false, 'message' => 'Commit SHA tidak valid.']);
}

if (!isset($_FILES['artifact']) || $_FILES['artifact']['error'] !== UPLOAD_ERR_OK) {
    respond(422, ['ok' => false, 'message' => 'File artifact tidak ditemukan atau gagal diunggah.']);
}

if ((int) $_FILES['artifact']['size'] > MAX_UPLOAD_BYTES) {
    respond(413, ['ok' => false, 'message' => 'Artifact melebihi 50 MB.']);
}

[$isValid, $validationMessage] = validZip((string) $_FILES['artifact']['tmp_name']);
if (!$isValid) {
    respond(422, ['ok' => false, 'message' => $validationMessage]);
}

$uploadDirectory = '/srv/autodeploy/uploads';
$uploadPath = $uploadDirectory . '/' . $project . '-' . bin2hex(random_bytes(12)) . '.zip';

if (!is_dir($uploadDirectory) || !move_uploaded_file((string) $_FILES['artifact']['tmp_name'], $uploadPath)) {
    respond(500, ['ok' => false, 'message' => 'Server gagal menyimpan artifact.']);
}

register_shutdown_function(static function () use ($uploadPath): void {
    if (is_file($uploadPath)) {
        unlink($uploadPath);
    }
});

try {
    $command = sprintf(
        'sudo /usr/local/sbin/autodeploy-project %s %s %s %s 2>&1',
        escapeshellarg($project),
        escapeshellarg($uploadPath),
        escapeshellarg($branch),
        escapeshellarg($commit)
    );

    $output = [];
    $exitCode = 1;
    exec($command, $output, $exitCode);

    $lastLine = $output === [] ? '' : (string) end($output);
    $result = json_decode($lastLine, true);

    if ($exitCode !== 0 || !is_array($result)) {
        error_log('AutoDeploy failed: ' . implode(PHP_EOL, $output));
        respond(500, [
            'ok' => false,
            'message' => 'Deployment gagal. Periksa log server.',
        ]);
    }

    respond(200, $result);
} finally {
    if (is_file($uploadPath)) {
        unlink($uploadPath);
    }
}
