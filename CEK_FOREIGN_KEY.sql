-- Jalankan di phpMyAdmin untuk memeriksa nama foreign key yang sudah terdaftar
-- pada database db_nusakarya. Ini hanya membaca metadata, tidak mengubah data.
SELECT
    CONSTRAINT_NAME,
    TABLE_NAME,
    REFERENCED_TABLE_NAME
FROM information_schema.REFERENTIAL_CONSTRAINTS
WHERE CONSTRAINT_SCHEMA = 'db_nusakarya'
ORDER BY CONSTRAINT_NAME, TABLE_NAME;
