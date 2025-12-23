# Sistem Absensi dengan Notifikasi WhatsApp

Aplikasi web untuk monitoring absensi karyawan dengan notifikasi WhatsApp otomatis.

## Fitur

- ✅ Dashboard statistik absensi realtime
- ✅ Notifikasi WhatsApp otomatis (check in/out, terlambat, tidak hadir, bolos)
- ✅ Laporan keterlambatan, tidak hadir, dan bolos
- ✅ Pengaturan jam kerja dan hari libur
- ✅ Log notifikasi dengan filter
- ✅ SPA dengan Vue.js 3
- ✅ Responsive design

## Requirements

- PHP >= 7.4
- MySQL/MariaDB
- Extension: mysqli, curl, json
- WhatsApp API Server (untuk notifikasi)

## Instalasi

### Cara 1: Menggunakan Installer (Recommended)

1. Copy semua file ke folder web server (htdocs/www)
2. Buka browser dan akses `http://localhost/folder/install.php`
3. Ikuti langkah-langkah instalasi

### Cara 2: Manual

1. Copy semua file ke folder web server
2. Edit `config.php` sesuai konfigurasi database:
```php
$conn = new mysqli('localhost', 'root', '', 'adms_db', 3306);
```
3. Edit `settings.json` untuk konfigurasi aplikasi

## Struktur Database

Aplikasi membutuhkan tabel berikut (dari mesin absensi):

- `userinfo` - Data karyawan (userid, name, badgenumber, Card)
- `checkinout` - Data absensi (id, userid, checktime, checktype)

> Kolom `Card` di tabel `userinfo` digunakan untuk menyimpan nomor WhatsApp

## Menjalankan Cron Job

### Windows
```batch
start_monitor.bat
```

### PowerShell
```powershell
.\start_monitor.ps1
```

### Linux (Crontab)
```bash
* * * * * php /path/to/cron_absensi.php
* * * * * php /path/to/cron_tidak_hadir.php
* * * * * php /path/to/cron_bolos.php
* * * * * php /path/to/cron_retry_queue.php
```

## File Penting

| File | Deskripsi |
|------|-----------|
| `app.php` | Aplikasi SPA (Vue.js) |
| `api.php` | Backend API |
| `config.php` | Konfigurasi database |
| `settings.php` | Helper pengaturan |
| `settings.json` | Data pengaturan |
| `cron_*.php` | Script cron job |
| `install.php` | Installer (hapus setelah instalasi) |

## Pengaturan Notifikasi

### Jam Kerja
- **Jam Masuk**: Batas waktu tepat waktu (default: 07:00)
- **Batas Terlambat**: Check in setelah jam masuk s/d jam ini = terlambat (default: 08:00)
- **Jam Pulang**: Tidak checkout setelah jam ini = bolos (default: 17:00)

### Hari Libur
Notifikasi tidak akan dikirim pada hari yang ditandai sebagai libur (default: Sabtu & Minggu)

## WhatsApp API

Aplikasi membutuhkan WhatsApp API server untuk mengirim notifikasi. Format request:

```json
POST /api/send-message
{
    "phone": "628123456789",
    "message": "Pesan notifikasi"
}
```

## License

MIT License
