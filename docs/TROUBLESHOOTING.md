# Troubleshooting

## GitHub Actions gagal sebelum mengirim

### Secret belum diisi

Gejala:

```text
Secret DEPLOY_URL belum diisi
```

Perbaikan: isi `DEPLOY_URL`, `DEPLOY_TOKEN`, dan `PROJECT_SLUG` pada **Settings → Secrets and variables → Actions** di repository aplikasi.

### PROJECT_SLUG tidak valid

Gunakan 3–40 karakter: huruf kecil, angka, dan tanda hubung. Contoh: `kelompok-01`.

## HTTP 401

Penyebab paling umum: token GitHub Secret berbeda dari `/etc/autodeploy/token`.

Periksa token di VPS sebagai root:

```bash
sudo cat /etc/autodeploy/token
```

Perbarui secret. Jangan mencetak token ke log Actions.

## HTTP 413

Artifact atau request lebih dari 50 MB. Cari file besar:

```bash
find . -type f -size +10M -print
```

Jangan sertakan dependency, backup, video, `.git`, atau arsip lain.

## HTTP 422

Deploy API menolak input. Baca pesan JSON. Penyebab yang umum:

- ZIP rusak;
- nama proyek tidak valid;
- `index.php` berada di dalam subfolder;
- paket mengandung path atau symbolic link yang tidak aman.

Struktur yang benar:

```text
application.zip
├── index.php
├── style.css
└── assets/
```

## HTTP 500

Lihat log:

```bash
sudo journalctl -u php*-fpm -n 100 --no-pager
sudo journalctl -u caddy -n 100 --no-pager
sudo tail -n 100 /srv/autodeploy/logs/deployments.log
docker ps -a
```

Cari candidate container, lalu baca log-nya:

```bash
docker ps -a --filter 'name=autodeploy-'
docker logs NAMA_CONTAINER
```

## HTTPS atau domain tidak aktif

Periksa DNS:

```bash
dig +short deploy.lab.example.ac.id
dig +short proyek.lab.example.ac.id
```

Periksa firewall dan listener:

```bash
sudo ufw status
sudo ss -lntp | grep -E ':80|:443'
```

Caddy membutuhkan akses internet dan port publik 80/443 untuk menerbitkan sertifikat.

## Aplikasi menampilkan Bad Gateway

Periksa port yang dituju Caddy:

```bash
sudo cat /etc/caddy/apps/NAMA_PROYEK.caddy
docker ps
curl -i http://127.0.0.1:PORT/
```

Jika container tidak hidup:

```bash
docker logs autodeploy-NAMA_PROYEK
docker inspect autodeploy-NAMA_PROYEK
```

## Disk penuh

Periksa:

```bash
df -h
docker system df
sudo du -sh /srv/autodeploy/projects/*
```

Untuk lingkungan praktikum, hapus image yang tidak lagi dipakai secara hati-hati:

```bash
docker image prune
```

Jangan memakai `docker system prune -a` tanpa memeriksa dampaknya.

## Mengganti token

Di VPS:

```bash
sudo sh -c 'openssl rand -hex 32 > /etc/autodeploy/token'
sudo chown root:www-data /etc/autodeploy/token
sudo chmod 0640 /etc/autodeploy/token
```

Perbarui `DEPLOY_TOKEN` pada seluruh repository yang masih diizinkan.

## Menghapus satu aplikasi

Identifikasi target terlebih dahulu:

```bash
docker ps -a --filter 'name=autodeploy-NAMA_PROYEK'
sudo ls -la /etc/caddy/apps/NAMA_PROYEK.caddy
sudo ls -la /srv/autodeploy/projects/NAMA_PROYEK
```

Kemudian hapus container dan konfigurasi yang tepat, lalu reload Caddy. Direktori release berisi data proyek; arsipkan dahulu jika masih diperlukan.
