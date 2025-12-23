-- =====================================================
-- SQL Script untuk Optimasi Index Database Absensi
-- Jalankan script ini di MySQL untuk meningkatkan performa query
-- =====================================================

-- Index untuk tabel checkinout
-- Index composite untuk query berdasarkan tanggal dan checktype
CREATE INDEX IF NOT EXISTS idx_checkinout_date_type 
ON checkinout (checktime, checktype);

-- Index untuk query berdasarkan userid dan tanggal
CREATE INDEX IF NOT EXISTS idx_checkinout_userid_date 
ON checkinout (userid, checktime);

-- Index untuk query ORDER BY id DESC (absensi terbaru)
-- Primary key sudah otomatis indexed, tapi pastikan ada
-- ALTER TABLE checkinout ADD PRIMARY KEY (id) jika belum ada

-- Index untuk tabel userinfo
-- Index untuk kolom Card (nomor WhatsApp)
CREATE INDEX IF NOT EXISTS idx_userinfo_card 
ON userinfo (Card);

-- Index untuk sorting berdasarkan nama
CREATE INDEX IF NOT EXISTS idx_userinfo_name 
ON userinfo (name);

-- =====================================================
-- Verifikasi Index yang sudah dibuat
-- =====================================================
SHOW INDEX FROM checkinout;
SHOW INDEX FROM userinfo;

-- =====================================================
-- Catatan:
-- 1. Jalankan ANALYZE TABLE setelah menambah index
--    ANALYZE TABLE checkinout;
--    ANALYZE TABLE userinfo;
-- 
-- 2. Untuk melihat execution plan query:
--    EXPLAIN SELECT ... (query anda)
-- =====================================================
