<?php

declare(strict_types=1);

$hostname = gethostname() ?: 'container';
$deployedAt = gmdate('d M Y, H:i:s') . ' UTC';
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Aplikasi Berhasil Di-deploy</title>
    <style>
        :root {
            color-scheme: light;
            font-family: Inter, ui-sans-serif, system-ui, sans-serif;
            background: #eef4ff;
            color: #172033;
        }

        body {
            min-height: 100vh;
            margin: 0;
            display: grid;
            place-items: center;
        }

        main {
            width: min(680px, calc(100% - 40px));
            padding: 36px;
            border-radius: 24px;
            background: #fff;
            box-shadow: 0 20px 60px rgb(36 76 140 / 16%);
        }

        .badge {
            display: inline-block;
            padding: 7px 12px;
            border-radius: 999px;
            background: #dcfce7;
            color: #166534;
            font-weight: 700;
        }

        h1 {
            margin: 20px 0 12px;
            font-size: clamp(2rem, 6vw, 3.7rem);
            line-height: 1;
        }

        p {
            color: #53627a;
            line-height: 1.7;
        }

        dl {
            display: grid;
            grid-template-columns: max-content 1fr;
            gap: 10px 18px;
            margin-top: 28px;
            padding: 20px;
            border-radius: 16px;
            background: #f7f9fd;
        }

        dt {
            color: #64748b;
        }

        dd {
            margin: 0;
            font-family: ui-monospace, monospace;
            overflow-wrap: anywhere;
        }
    </style>
</head>
<body>
<main>
    <span class="badge">● Container aktif</span>
    <h1>Hello, AutoDeploy!</h1>
    <p>
        Halaman PHP ini dikirim oleh GitHub Actions, dibangun menjadi image,
        dijalankan di Docker, lalu diteruskan oleh reverse proxy Caddy.
    </p>
    <dl>
        <dt>PHP</dt>
        <dd><?= htmlspecialchars(PHP_VERSION, ENT_QUOTES, 'UTF-8') ?></dd>
        <dt>Container</dt>
        <dd><?= htmlspecialchars($hostname, ENT_QUOTES, 'UTF-8') ?></dd>
        <dt>Waktu akses</dt>
        <dd><?= htmlspecialchars($deployedAt, ENT_QUOTES, 'UTF-8') ?></dd>
    </dl>
</main>
</body>
</html>
