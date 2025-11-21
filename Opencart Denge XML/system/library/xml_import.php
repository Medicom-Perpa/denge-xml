<?php

class xml_import {

    protected $registry;
    protected $log_file;
    protected $log_dir;
    protected $xml_dir;

    public function __construct($registry) {
        $this->registry = $registry;

        $this->log_dir  = DIR_STORAGE . 'logs/';
        $this->log_file = $this->log_dir . 'xml_import.log';
        $this->xml_dir  = DIR_STORAGE . 'xml/';

        // Klasörler yoksa oluştur
        if (!is_dir($this->log_dir)) {
            mkdir($this->log_dir, 0755, true);
        }

        if (!is_dir($this->xml_dir)) {
            mkdir($this->xml_dir, 0755, true);
        }

        // 🎯 IMPORT BAŞLAMADAN ÖNCE ANA LOG DOSYASINI SIFIRLA
        if (file_exists($this->log_file)) {
            file_put_contents($this->log_file, "");
        }

        // Eski günlük logları temizle
        $this->cleanupOldLogs();
    }

    public function getXmlDir() {
        return $this->xml_dir;
    }

    // 🎯 Log sistemi
    public function log($message) {
        $date = date('Y-m-d H:i:s');
        $line = '[' . $date . '] ' . $message . "\n";

        // Ana log (her import öncesi sıfırlandığı için temiz yazılır)
        file_put_contents($this->log_file, $line, FILE_APPEND);

        // Günlük log
        $daily_file = $this->log_dir . 'xml_import_' . date('Y-m-d') . '.txt';
        file_put_contents($daily_file, $line, FILE_APPEND);
    }

    // 🎯 7 günlük eski günlük log temizliği
    protected function cleanupOldLogs() {
        $files = glob($this->log_dir . 'xml_import_*.txt');
        if (!$files) return;

        $now = time();
        $limit = 7 * 24 * 60 * 60; // 7 gün

        foreach ($files as $file) {
            if (is_file($file)) {
                if ($now - filemtime($file) > $limit) {
                    @unlink($file);
                }
            }
        }
    }

    // 🎯 XML indirme fonksiyonu (CURL ile)
    public function downloadXML($url, $filename = 'products.xml') {

        $this->log("XML indirme başladı: $url");

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        // SSL doğrulama kapalı
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        // Bot engelini aşmak için User-Agent
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0");

        $xml = curl_exec($ch);

        $curl_error = curl_error($ch);
        $http_code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        // CURL hata logu
        if (!empty($curl_error)) {
            $this->log("CURL HATA: " . $curl_error);
            return false;
        }

        // HTTP cevap kodu 200 değilse hata
        if ($http_code != 200) {
            $this->log("HATA: HTTP Kod: " . $http_code);
            return false;
        }

        // İçerik boşsa
        if (!$xml || strlen($xml) < 20) {
            $this->log("HATA: XML içeriği boş veya eksik (0 byte).");
            return false;
        }

        // Dosyayı kaydet
        $savePath = $this->xml_dir . $filename;
        file_put_contents($savePath, $xml);

        $size = @filesize($savePath);
        $this->log("XML başarıyla indirildi. Dosya: {$savePath} | Boyut: {$size} byte");

        return $savePath;
    }
}
