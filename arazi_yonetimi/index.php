<?php
include_once 'config.php';

$database = new Database();
$db = $database->getConnection();

$arazi = new Arazi($db);
$toprak = new ToprakAnalizi($db);

// Form işlemleri
if ($_POST) {
    if (isset($_POST['arazi_ekle'])) {
        if ($arazi->araziEkle($_POST['arazi_adi'], $_POST['sahip_id'], $_POST['enlem'], $_POST['boylam'], $_POST['alan_m2'])) {
            $mesaj = "Arazi başarıyla eklendi!";
        } else {
            $hata = "Arazi eklenirken hata oluştu!";
        }
    }
    
    if (isset($_POST['analiz_ekle'])) {
        if ($toprak->analizEkle($_POST['arazi_id'], $_POST['ph'], $_POST['nem'], $_POST['organik'], $_POST['azot'], $_POST['fosfor'], $_POST['potasyum'])) {
            $mesaj = "Toprak analizi başarıyla eklendi!";
        } else {
            $hata = "Analiz eklenirken hata oluştu!";
        }
    }
}

$araziler = $arazi->tumArazileriGetir();
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Arazi Yönetim Sistemi</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        .header { background: #2c3e50; color: white; padding: 15px; text-align: center; margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        .btn { background: #3498db; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
        .btn:hover { background: #2980b9; }
        .success { background: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
        .error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px; }
        table { width: 100%; border-collapse: collapse; }
        table th, table td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        table th { background-color: #f8f9fa; }
        .python-btn { background: #e74c3c; }
        .python-btn:hover { background: #c0392b; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🌾 Arazi Yönetim Sistemi</h1>
        </div>

        <?php if (isset($mesaj)): ?>
            <div class="success"><?php echo $mesaj; ?></div>
        <?php endif; ?>
        
        <?php if (isset($hata)): ?>
            <div class="error"><?php echo $hata; ?></div>
        <?php endif; ?>

        <div class="grid">
            <!-- Arazi Ekleme Formu -->
            <div>
                <h3>🏞️ Yeni Arazi Ekle</h3>
                <form method="post">
                    <div class="form-group">
                        <label>Arazi Adı:</label>
                        <input type="text" name="arazi_adi" required>
                    </div>
                    <div class="form-group">
                        <label>Sahip ID:</label>
                        <input type="number" name="sahip_id" value="2" required>
                    </div>
                    <div class="form-group">
                        <label>Enlem:</label>
                        <input type="number" step="0.000001" name="enlem" placeholder="40.123456" required>
                    </div>
                    <div class="form-group">
                        <label>Boylam:</label>
                        <input type="number" step="0.000001" name="boylam" placeholder="32.123456" required>
                    </div>
                    <div class="form-group">
                        <label>Alan (m²):</label>
                        <input type="number" name="alan_m2" required>
                    </div>
                    <button type="submit" name="arazi_ekle" class="btn">Arazi Ekle</button>
                </form>
            </div>

            <!-- Toprak Analizi Formu -->
            <div>
                <h3>🧪 Toprak Analizi Ekle</h3>
                <form method="post">
                    <div class="form-group">
                        <label>Arazi:</label>
                        <select name="arazi_id" required>
                            <option value="">Arazi Seçin</option>
                            <?php
                            $araziler_select = $arazi->tumArazileriGetir();
                            while ($row = $araziler_select->fetch(PDO::FETCH_ASSOC)) {
                                echo "<option value='{$row['id']}'>{$row['arazi_adi']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>pH Değeri:</label>
                        <input type="number" step="0.01" name="ph" placeholder="6.5" required>
                    </div>
                    <div class="form-group">
                        <label>Nem Oranı (%):</label>
                        <input type="number" step="0.01" name="nem" placeholder="25.5" required>
                    </div>
                    <div class="form-group">
                        <label>Organik Madde (%):</label>
                        <input type="number" step="0.01" name="organik" placeholder="3.2" required>
                    </div>
                    <div class="form-group">
                        <label>Azot (%):</label>
                        <input type="number" step="0.01" name="azot" placeholder="2.1" required>
                    </div>
                    <div class="form-group">
                        <label>Fosfor (%):</label>
                        <input type="number" step="0.01" name="fosfor" placeholder="1.5" required>
                    </div>
                    <div class="form-group">
                        <label>Potasyum (%):</label>
                        <input type="number" step="0.01" name="potasyum" placeholder="2.8" required>
                    </div>
                    <button type="submit" name="analiz_ekle" class="btn">Analiz Ekle</button>
                </form>
            </div>
        </div>

        <!-- Python Analiz Butonu -->
        <div style="text-align: center; margin: 20px 0;">
            <form action="python_runner.php" method="post">
                <button type="submit" class="btn python-btn">🐍 Python ile Uygunluk Analizi Çalıştır</button>
            </form>
        </div>

        <!-- Arazi Listesi -->
        <h3>📋 Kayıtlı Araziler</h3>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Arazi Adı</th>
                    <th>Sahip</th>
                    <th>Koordinatlar</th>
                    <th>Alan (m²)</th>
                    <th>Durum</th>
                    <th>Kayıt Tarihi</th>
                </tr>
            </thead>
            <tbody>
                <?php
                while ($row = $araziler->fetch(PDO::FETCH_ASSOC)) {
                    echo "<tr>";
                    echo "<td>{$row['id']}</td>";
                    echo "<td>{$row['arazi_adi']}</td>";
                    echo "<td>{$row['sahip_adi']}</td>";
                    echo "<td>{$row['enlem']}, {$row['boylam']}</td>";
                    echo "<td>" . number_format($row['alan_m2']) . "</td>";
                    echo "<td>{$row['durum']}</td>";
                    echo "<td>" . date('d.m.Y', strtotime($row['kayit_tarihi'])) . "</td>";
                    echo "</tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</body>
</html>