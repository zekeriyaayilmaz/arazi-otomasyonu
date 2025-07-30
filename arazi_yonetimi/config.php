<?php
// config.php - Veritabanı bağlantısı
class Database {
    private $host = 'localhost';
    private $db_name = 'arazi_yonetimi';
    private $username = 'root';
    private $password = '';
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->exec("set names utf8");
        } catch(PDOException $exception) {
            echo "Bağlantı hatası: " . $exception->getMessage();
        }
        return $this->conn;
    }
}

// Arazi sınıfı
class Arazi {
    private $conn;
    private $table_name = "araziler";

    public function __construct($db) {
        $this->conn = $db;
    }

    // Tüm arazileri listele
    public function tumArazileriGetir() {
        $query = "SELECT a.*, k.kullanici_adi as sahip_adi 
                  FROM " . $this->table_name . " a 
                  LEFT JOIN kullanicilar k ON a.sahip_id = k.id 
                  ORDER BY a.kayit_tarihi DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    // Yeni arazi ekle
    public function araziEkle($arazi_adi, $sahip_id, $enlem, $boylam, $alan_m2) {
        $query = "INSERT INTO " . $this->table_name . " 
                  SET arazi_adi=:arazi_adi, sahip_id=:sahip_id, enlem=:enlem, boylam=:boylam, alan_m2=:alan_m2";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(":arazi_adi", $arazi_adi);
        $stmt->bindParam(":sahip_id", $sahip_id);
        $stmt->bindParam(":enlem", $enlem);
        $stmt->bindParam(":boylam", $boylam);
        $stmt->bindParam(":alan_m2", $alan_m2);
        
        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Arazi sil
    public function araziSil($id) {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $id);
        
        if($stmt->execute()) {
            return true;
        }
        return false;
    }
}

// Toprak Analizi sınıfı
class ToprakAnalizi {
    private $conn;
    private $table_name = "toprak_analizi";

    public function __construct($db) {
        $this->conn = $db;
    }

    // Analiz ekle
    public function analizEkle($arazi_id, $ph, $nem, $organik, $azot, $fosfor, $potasyum) {
        $query = "INSERT INTO " . $this->table_name . " 
                  SET arazi_id=:arazi_id, ph_degeri=:ph, nem_orani=:nem, 
                      organik_madde=:organik, azot=:azot, fosfor=:fosfor, potasyum=:potasyum, 
                      analiz_tarihi=CURDATE()";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(":arazi_id", $arazi_id);
        $stmt->bindParam(":ph", $ph);
        $stmt->bindParam(":nem", $nem);
        $stmt->bindParam(":organik", $organik);
        $stmt->bindParam(":azot", $azot);
        $stmt->bindParam(":fosfor", $fosfor);
        $stmt->bindParam(":potasyum", $potasyum);
        
        return $stmt->execute();
    }

    // Arazi analiz geçmişi
    public function araziAnalizleri($arazi_id) {
        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE arazi_id = ? ORDER BY analiz_tarihi DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $arazi_id);
        $stmt->execute();
        return $stmt;
    }
}
?>