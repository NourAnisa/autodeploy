# Modul Praktikum: AutoDeploy PHP Native di VPS

## Identitas

- Mata kuliah: Layanan dan Sistem Virtual
- Topik: VPS, container, reverse proxy, dan continuous deployment
- Sasaran: mahasiswa pemula
- Durasi: 100–150 menit
- Bentuk: demonstrasi dosen dilanjutkan praktik kelompok

## Capaian pembelajaran

Setelah praktikum, mahasiswa mampu:

1. menjelaskan hubungan server fisik, hypervisor, VPS, container, dan aplikasi;
2. menghubungkan domain ke alamat IP VPS;
3. menjelaskan alur deployment dari `git push` hingga aplikasi dapat diakses;
4. menjalankan aplikasi PHP dalam container dengan batas resource;
5. membedakan port publik dan port lokal;
6. menyimpan token sebagai GitHub Actions secret;
7. membaca log untuk menemukan penyebab deployment gagal.

## Gambaran sistem

| Lapisan | Komponen | Fungsi |
|---|---|---|
| Cloud/VPS | Ubuntu Server | Mesin virtual yang mempunyai IP publik |
| Otomasi | GitHub Actions | Membuat dan mengirim artifact |
| Control plane | Deploy API PHP | Memeriksa token, nama proyek, dan ZIP |
| Runtime | Docker | Mengisolasi setiap aplikasi |
| Jaringan | Caddy | Mengarahkan subdomain ke port container |
| DNS | A record/wildcard | Mengubah nama domain menjadi IP VPS |

Analogi sederhana: VPS adalah gedung, Docker adalah ruang kelas, port adalah nomor ruangan, Caddy adalah resepsionis, dan DNS adalah alamat gedung.

## Persiapan dosen sebelum kelas

- Satu VPS Ubuntu 24.04 LTS, minimal 2 vCPU/4 GB RAM.
- Satu domain atau subdomain yang dapat diatur DNS-nya.
- Repository ini.
- Satu repository aplikasi contoh untuk tiap kelompok, atau satu repository yang di-fork.
- Port TCP 22, 80, dan 443 dapat diakses.
- DNS berikut sudah mengarah ke IP VPS:
  - `deploy.lab.example.ac.id`
  - `*.lab.example.ac.id`

Tunggu propagasi DNS dan periksa:

```bash
dig +short deploy.lab.example.ac.id
dig +short kelompok-01.lab.example.ac.id
```

Keduanya harus mengembalikan IP publik VPS.

## Bagian A — Mengenali VPS

Masuk melalui SSH:

```bash
ssh root@IP_VPS
```

Amati resource virtual:

```bash
hostnamectl
lscpu
free -h
lsblk
ip addr
```

Pertanyaan diskusi:

1. Apakah jumlah vCPU sama dengan jumlah prosesor fisik?
2. Mengapa VPS memiliki IP sendiri?
3. Apa yang terjadi jika seluruh container menggunakan RAM tanpa batas?

## Bagian B — Memasang AutoDeploy

Di VPS:

```bash
apt update
apt install -y git
git clone https://github.com/NourAnisa/autodeploy.git
cd autodeploy
sudo bash server/install.sh lab.example.ac.id
```

Ganti base domain sesuai domain praktikum. Simpan output `Deploy URL` dan `Token`. Jangan menampilkan token pada screenshot laporan atau menaruhnya di source code.

Periksa layanan:

```bash
systemctl status docker --no-pager
systemctl status caddy --no-pager
ss -lntp
```

Hasil yang diharapkan:

- Docker berstatus `active (running)`;
- Caddy berstatus `active (running)`;
- port 80 dan 443 digunakan Caddy;
- Deploy API dapat dijangkau melalui HTTPS.

Uji API tanpa token:

```bash
curl -i -X POST https://deploy.lab.example.ac.id/deploy.php
```

Respons `401 Unauthorized` atau `413` menunjukkan endpoint hidup dan menolak request yang tidak sah.

## Bagian C — Menyiapkan aplikasi mahasiswa

Buat repository GitHub baru, lalu masukkan `index.php`:

```php
<?php
echo '<h1>Halo dari Kelompok 01</h1>';
echo '<p>Server: ' . htmlspecialchars(gethostname()) . '</p>';
```

Salin file workflow dari repository AutoDeploy:

```text
examples/workflows/deploy.yml
```

ke:

```text
.github/workflows/deploy.yml
```

Pada repository aplikasi buka **Settings → Secrets and variables → Actions**, lalu buat:

| Nama | Isi |
|---|---|
| `DEPLOY_URL` | URL endpoint dari installer |
| `DEPLOY_TOKEN` | token rahasia dari installer |
| `PROJECT_SLUG` | misalnya `kelompok-01` |

Jangan menggunakan slug yang sama untuk dua kelompok.

## Bagian D — Deployment pertama

Commit dan push:

```bash
git add .
git commit -m "feat: aplikasi pertama"
git push origin main
```

Buka tab **Actions**. Amati tiga tahap:

1. checkout source code;
2. membuat `application.zip`;
3. mengirim artifact ke Deploy API.

Jika sukses, respons berisi URL aplikasi. Buka URL tersebut di browser.

## Bagian E — Mengamati server

Di VPS:

```bash
docker ps
docker image ls
docker stats --no-stream
sudo cat /etc/caddy/apps/kelompok-01.caddy
sudo tail -n 20 /srv/autodeploy/logs/deployments.log
```

Hubungkan temuan dengan konsep:

| Temuan | Konsep |
|---|---|
| Nama image dan container | image versus instance |
| `127.0.0.1:PORT->80/tcp` | port mapping dan loopback |
| `--memory 256m` | resource limitation |
| File Caddy | reverse proxy |
| URL HTTPS | TLS dan sertifikat |
| Baris log release | audit deployment |

## Bagian F — Redeploy

Ubah judul di `index.php`, lalu push kembali. Perhatikan bahwa:

- image baru dibuat;
- candidate container diperiksa lebih dahulu;
- reverse proxy diarahkan ke container baru;
- container lama dihapus setelah candidate sehat;
- tiga release terakhir dipertahankan.

Diskusikan: mengapa health check dilakukan sebelum container lama dihapus?

## Percobaan kegagalan terkontrol

Lakukan satu per satu, lalu kembalikan ke kondisi benar:

1. hapus `index.php`;
2. isi `PROJECT_SLUG` dengan spasi;
3. ganti satu karakter `DEPLOY_TOKEN`;
4. buat PHP mengembalikan HTTP 500 pada halaman utama.

Catat bagian mana yang menolak deployment: GitHub Actions, Deploy API, atau health check.

## Tugas mahasiswa

Buat aplikasi profil kelompok yang memuat:

- nama dan anggota kelompok;
- waktu server dalam UTC;
- hostname container;
- satu halaman tambahan;
- desain CSS sederhana.

Kumpulkan:

1. URL repository;
2. URL aplikasi;
3. screenshot GitHub Actions berhasil;
4. screenshot `docker ps`;
5. diagram alur deployment;
6. refleksi 150–250 kata tentang perbedaan VPS dan container.

## Rubrik penilaian

| Aspek | Bobot |
|---|---:|
| Aplikasi dapat diakses melalui HTTPS/subdomain | 25% |
| Workflow berhasil dan secrets tidak bocor | 20% |
| Pemahaman VPS, Docker, port, DNS, reverse proxy | 25% |
| Bukti observasi dan analisis log | 15% |
| Kualitas aplikasi dan dokumentasi | 15% |

## Pertanyaan evaluasi

1. Mengapa aplikasi tidak langsung membuka port publik sendiri?
2. Apa manfaat satu subdomain untuk setiap proyek?
3. Mengapa token disimpan sebagai secret?
4. Apa perbedaan image dan container?
5. Apa risiko menjalankan kode mahasiswa pada satu Docker host?
6. Apakah sistem ini termasuk layanan virtual? Jelaskan lapisan virtualisasinya.
7. Apa yang harus ditambah sebelum sistem dipakai untuk publik?

## Penutupan konsep

Praktikum ini termasuk mata kuliah Layanan dan Sistem Virtual karena memakai beberapa lapisan sekaligus: VPS mengabstraksi server fisik, container mengisolasi proses aplikasi, dan platform AutoDeploy mengabstraksi pekerjaan administrasi deployment. Mahasiswa tidak hanya “mengunggah web”, tetapi mengamati bagaimana layanan virtual dibangun dan dioperasikan.
