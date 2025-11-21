<?php
class ControllerExtensionModuleXmlImport extends Controller {

    public function cron() {
        $this->runProcess();
    }

    public function run_now() {
        $this->runProcess();
    }

    /**
     * ANA ÇALIŞMA İŞLEMİ
     */
    protected function runProcess() {

        if (!$this->config->get('module_xml_import_status')) {
            echo "Modül pasif.";
            return;
        }

        $this->load->library('xml_import');
        $this->load->model('extension/module/xml_import');

        $this->xml_import->log("=== XML IMPORT BAŞLADI ===");

        /**
         * 1) ADMIN PANELDEN ALINAN AYARLAR
         */
        $product_url  = html_entity_decode($this->config->get('module_xml_import_url'), ENT_QUOTES, 'UTF-8');
        $category_url = html_entity_decode($this->config->get('module_xml_import_category_url'), ENT_QUOTES, 'UTF-8');
        $currency     = $this->config->get('module_xml_import_currency');

        if (!$currency) $currency = 'USD';

        // URL kontrol
        if (!$category_url) {
            $this->xml_import->log("HATA: Kategori XML URL tanımlı değil.");
            echo "Kategori XML URL tanımlı değil.";
            return;
        }

        if (!$product_url) {
            $this->xml_import->log("HATA: Ürün XML URL tanımlı değil.");
            echo "Ürün XML URL tanımlı değil.";
            return;
        }

        $this->xml_import->log("Kategori XML URL: {$category_url}");
        $this->xml_import->log("Ürün XML URL: {$product_url}");
        $this->xml_import->log("Döviz: {$currency}");

        /**
         * 2) KUR ÇEK
         */
        $rate = $this->model_extension_module_xml_import->getRate($currency);
        $this->xml_import->log("Kur: {$rate}");

        /**
         * 3) XML KAYIT KLASÖRÜ (storage/xml)
         */
        $xml_dir = DIR_STORAGE . 'xml/';

        if (!is_dir($xml_dir)) {
            mkdir($xml_dir, 0777, true);
        }

        /**
         * 4) KATEGORİ XML İNDİR
         */
        $this->xml_import->log("Kategori XML indiriliyor...");
        $category_xml_path = $this->xml_import->downloadXML($category_url, 'denge_categories.xml');

        if (!$category_xml_path) {
            $this->xml_import->log("HATA: Kategori XML indirilemedi!");
            echo "Kategori XML indirilemedi.";
            return;
        }

        $this->xml_import->log("Kategori XML indirildi → {$category_xml_path}");

        /**
         * 5) KATEGORİLERİ İMPORT ET
         */
        $this->model_extension_module_xml_import->importCategories($category_xml_path);


        /**
         * 6) ÜRÜN XML İNDİR
         */
        $this->xml_import->log("Ürün XML indiriliyor...");
        $product_xml_path = $this->xml_import->downloadXML($product_url, 'denge_products.xml');

        if (!$product_xml_path) {
            $this->xml_import->log("HATA: Ürün XML indirilemedi!");
            echo "Ürün XML indirilemedi.";
            return;
        }

        $this->xml_import->log("Ürün XML indirildi → {$product_xml_path}");

        /**
         * 7) ÜRÜNLERİ İMPORT ET
         */
        $this->model_extension_module_xml_import->importProducts($product_xml_path, $rate);

        $this->xml_import->log("=== XML IMPORT BİTTİ ===");

        echo "OK";
    }
}
