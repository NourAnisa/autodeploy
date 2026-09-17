# Demo Gratis dengan VirtualBox

Panduan ini menjalankan AutoDeploy sepenuhnya pada laptop. Tidak diperlukan VPS, domain, kartu kredit, atau biaya langganan.

## 1. Buat virtual machine

Pasang VirtualBox dan unduh Ubuntu Server 24.04 LTS. Buat VM dengan:

- CPU: 2 core
- RAM: 4 GB
- Disk: 30 GB
- Network: Bridged Adapter
- Centang instalasi OpenSSH Server saat memasang Ubuntu

Bridged Adapter membuat VM memperoleh IP dari Wi-Fi/router yang sama dengan laptop.

## 2. Masuk ke Ubuntu

Login dari jendela VirtualBox atau melalui SSH:

```bash
ssh NAMA_USER@IP_VM
```

Lihat IP dengan:

```bash
hostname -I
```

## 3. Instal AutoDeploy lokal

```bash
sudo apt update
sudo apt install -y git
git clone https://github.com/NourAnisa/autodeploy.git
cd autodeploy
sudo bash server/install-local.sh
```

Installer otomatis membuat alamat gratis berbasis IP melalui `sslip.io`. Mode lokal menggunakan HTTP karena IP privat tidak dapat menerima sertifikat HTTPS publik.

## 4. Deploy aplikasi contoh

Masih dari direktori repository:

```bash
bash examples/deploy-local.sh demo-app examples/php-native
```

Script akan meminta password sudo untuk membaca token lokal, membuat ZIP, mengirimnya ke Deploy API, membangun image Docker, menjalankan container, dan menampilkan URL.

Contoh URL:

```text
http://demo-app.192-168-1-50.sslip.io
```

Buka URL tersebut dari browser laptop yang berada pada jaringan yang sama.

## 5. Amati virtualisasi

```bash
docker ps
docker image ls
docker stats --no-stream
sudo cat /etc/caddy/apps/demo-app.caddy
sudo tail -n 20 /srv/autodeploy/logs/deployments.log
```

## Jika URL tidak dapat dibuka

Periksa alamat IP:

```bash
hostname -I
```

Pastikan VirtualBox memakai Bridged Adapter, laptop dan VM berada pada jaringan yang sama, serta firewall mengizinkan HTTP:

```bash
sudo ufw allow 80/tcp
```

Jika jaringan kampus memblokir komunikasi antarklien, gunakan NAT dengan Port Forwarding host port 8080 ke guest port 80. Dalam kondisi itu akses melalui `http://localhost:8080`, tetapi subdomain aplikasi tidak akan bekerja tanpa pengaturan hosts tambahan.

## Batas mode lokal

GitHub-hosted Actions tidak dapat menghubungi IP privat VM. Karena itu mode ini memakai script lokal yang meniru langkah workflow: membuat artifact ZIP, mengirim request, memvalidasi paket, build image, health check, dan mengaktifkan aplikasi. Mode VPS tetap tersedia untuk demonstrasi GitHub Actions secara penuh.
