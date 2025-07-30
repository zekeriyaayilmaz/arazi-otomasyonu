-- Arazi Yönetimi Veritabanı
CREATE DATABASE arazi_yonetimi;
USE arazi_yonetimi;

-- Kullanıcılar tablosu
CREATE TABLE kullanicilar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kullanici_adi VARCHAR(50) UNIQUE NOT NULL,
    sifre VARCHAR(255) NOT NULL,
    email VARCHAR(100),
    rol ENUM('admin', 'kullanici') DEFAULT 'kullanici',
    kayit_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Araziler tablosu
CREATE TABLE araziler (
    id INT AUTO_INCREMENT PRIMARY KEY,
    arazi_adi VARCHAR(100) NOT NULL,
    sahip_id INT,
    enlem DECIMAL(10,8),
    boylam DECIMAL(11,8),
    alan_m2 INT,
    durum ENUM('aktif', 'pasif', 'kirali') DEFAULT 'aktif',
    kayit_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sahip_id) REFERENCES kullanicilar(id)
);

-- Toprak analizi tablosu
CREATE TABLE toprak_analizi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    arazi_id INT,
    ph_degeri DECIMAL(3,2),
    nem_orani DECIMAL(5,2),
    organik_madde DECIMAL(5,2),
    azot DECIMAL(5,2),
    fosfor DECIMAL(5,2),
    potasyum DECIMAL(5,2),
    analiz_tarihi DATE,
    FOREIGN KEY (arazi_id) REFERENCES araziler(id)
);

-- Ekin türleri tablosu
CREATE TABLE ekin_turleri (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ekin_adi VARCHAR(50) NOT NULL,
    min_ph DECIMAL(3,2),
    max_ph DECIMAL(3,2),
    min_nem DECIMAL(5,2),
    max_nem DECIMAL(5,2),
    gerekli_azot DECIMAL(5,2),
    gerekli_fosfor DECIMAL(5,2),
    gerekli_potasyum DECIMAL(5,2)
);

-- Uygunluk haritası tablosu
CREATE TABLE uygunluk_haritasi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    arazi_id INT,
    ekin_id INT,
    uygunluk_skoru DECIMAL(5,2),
    hesaplama_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (arazi_id) REFERENCES araziler(id),
    FOREIGN KEY (ekin_id) REFERENCES ekin_turleri(id)
);

-- Örnek veriler
INSERT INTO kullanicilar (kullanici_adi, sifre, email, rol) VALUES 
('admin', MD5('123456'), 'admin@test.com', 'admin'),
('ahmet', MD5('123456'), 'ahmet@test.com', 'kullanici');

INSERT INTO ekin_turleri (ekin_adi, min_ph, max_ph, min_nem, max_nem, gerekli_azot, gerekli_fosfor, gerekli_potasyum) VALUES
('Buğday', 6.0, 7.5, 15, 25, 2.5, 1.2, 2.0),
('Mısır', 6.0, 6.8, 20, 30, 3.0, 1.5, 2.5),
('Domates', 6.0, 7.0, 25, 35, 3.5, 2.0, 3.0),
('Patates', 5.2, 6.0, 18, 28, 2.0, 1.0, 2.8);