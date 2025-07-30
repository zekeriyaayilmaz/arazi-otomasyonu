<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

class PythonRunner {
    private $pythonPath;
    private $scriptPath;

    public function __construct() {
        $this->pythonPath = 'C:\\Users\\zeker\\AppData\\Local\\Programs\\Python\\Python313\\python.exe';
        $this->scriptPath = __DIR__ . DIRECTORY_SEPARATOR . 'uygunluk_analizi.py';
    }

    public function runAnalysis() {
        try {
            // Environment variables
            putenv('PYTHONIOENCODING=utf-8');
            putenv('PYTHONUTF8=1');
            
            // Windows için UTF-8
            shell_exec('chcp 65001');
            
            // Python komutu
            $command = sprintf('"%s" -X utf8 "%s"', $this->pythonPath, $this->scriptPath);
            
            // Descriptors
            $descriptorspec = array(
                0 => array("pipe", "r"),  // stdin
                1 => array("pipe", "w"),  // stdout
                2 => array("pipe", "w")   // stderr
            );
            
            // Process başlat
            $process = proc_open($command, $descriptorspec, $pipes, __DIR__);
            
            if (is_resource($process)) {
                // Stdin'i kapat
                fclose($pipes[0]);
                
                // stdout ve stderr'i oku
                $output = '';
                $error = '';
                
                while (!feof($pipes[1])) {
                    $output .= fread($pipes[1], 8192);
                }
                
                while (!feof($pipes[2])) {
                    $error .= fread($pipes[2], 8192);
                }
                
                fclose($pipes[1]);
                fclose($pipes[2]);
                
                $return_value = proc_close($process);
                
                // UTF-8'e dönüştür
                $output = mb_convert_encoding($output, 'UTF-8', 'ASCII,UTF-8,ISO-8859-1');
                $error = mb_convert_encoding($error, 'UTF-8', 'ASCII,UTF-8,ISO-8859-1');
                
                return [
                    'success' => ($return_value === 0),
                    'output' => $output,
                    'error' => $error,
                    'return_code' => $return_value
                ];
            }
            
            throw new Exception("Process başlatılamadı");

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'command' => $command ?? null
            ];
        }
    }
}

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Arazi Uygunluk Analizi</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f5f5f5; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header { background: #2c3e50; color: white; padding: 15px; text-align: center; margin-bottom: 20px; border-radius: 4px; }
        .result { margin: 20px 0; padding: 15px; border-radius: 4px; }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto; white-space: pre-wrap; }
        .btn { background: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block; }
        .btn:hover { background: #2980b9; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Arazi Uygunluk Analizi</h1>
        </div>

        <a href="index.php" class="btn">← Ana Sayfaya Dön</a>

        <div class="result">
            <?php
            $runner = new PythonRunner();
            $result = $runner->runAnalysis();

            if ($result['success']) {
                echo '<div class="success">';
                echo '<h3>✓ Analiz Başarıyla Tamamlandı!</h3>';
                
                if (file_exists('uygunluk_haritasi.html')) {
                    echo '<p><a href="uygunluk_haritasi.html" target="_blank">Uygunluk Haritasını Görüntüle</a></p>';
                }
                
                if (!empty($result['output'])) {
                    echo '<h4>Analiz Çıktısı:</h4>';
                    echo '<pre>' . htmlspecialchars($result['output'], ENT_QUOTES, 'UTF-8') . '</pre>';
                }
                echo '</div>';
            } else {
                echo '<div class="error">';
                echo '<h3>Analiz Sırasında Hata Oluştu</h3>';
                if (!empty($result['error'])) {
                    echo '<p>Hata: ' . htmlspecialchars($result['error'], ENT_QUOTES, 'UTF-8') . '</p>';
                }
                if (!empty($result['output'])) {
                    echo '<p>Çıktı: ' . htmlspecialchars($result['output'], ENT_QUOTES, 'UTF-8') . '</p>';
                }
                if (isset($result['return_code'])) {
                    echo '<p>Dönüş Kodu: ' . $result['return_code'] . '</p>';
                }
                if (isset($result['command'])) {
                    echo '<p>Çalıştırılan Komut: ' . htmlspecialchars($result['command'], ENT_QUOTES, 'UTF-8') . '</p>';
                }
                echo '</div>';
            }
            ?>
        </div>

        <?php if (file_exists('uygunluk_haritasi.html')): ?>
        <div class="result">
            <h3>Uygunluk Haritası</h3>
            <iframe src="uygunluk_haritasi.html" width="100%" height="500" style="border: 1px solid #ddd; border-radius: 4px;"></iframe>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>