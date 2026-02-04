-- Optimasi Index untuk Database Absensi
-- Jalankan query ini untuk meningkatkan performa query

-- Index untuk tabel checkinout (tabel utama absensi)
CREATE INDEX IF NOT EXISTS idx_checkinout_checktime ON checkinout(checktime);
CREATE INDEX IF NOT EXISTS idx_checkinout_userid ON checkinout(userid);
CREATE INDEX IF NOT EXISTS idx_checkinout_checktype ON checkinout(checktype);
CREATE INDEX IF NOT EXISTS idx_checkinout_date_type ON checkinout(checktime, checktype);
CREATE INDEX IF NOT EXISTS idx_checkinout_user_date ON checkinout(userid, checktime);

-- Index untuk tabel userinfo
CREATE INDEX IF NOT EXISTS idx_userinfo_name ON userinfo(name);
CREATE INDEX IF NOT EXISTS idx_userinfo_title ON userinfo(title);
CREATE INDEX IF NOT EXISTS idx_userinfo_badgenumber ON userinfo(badgenumber);
CREATE INDEX IF NOT EXISTS idx_userinfo_card ON userinfo(Card);

-- Composite index untuk query laporan
CREATE INDEX IF NOT EXISTS idx_checkinout_composite ON checkinout(checktype, checktime, userid);

-- Analyze tables untuk update statistics
ANALYZE TABLE checkinout;
ANALYZE TABLE userinfo;

-- Optimize tables (defragment)
OPTIMIZE TABLE checkinout;
OPTIMIZE TABLE userinfo;
