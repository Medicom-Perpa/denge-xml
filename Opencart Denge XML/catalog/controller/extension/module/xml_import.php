<?php
class ControllerExtensionModuleXmlImport extends Controller {

    public function cron() {
        $this->runProcess();
    }

    public function run_now() {
        $this->runProcess();
    }

    protected function runProcess() {

        if (!$this->config->get('module_xml_import_status')) {
            return;
        }

        $this->load->library('xml_import');
        $this->load->model('extension/module/xml_import');

        $this->xml_import->log("=== XML IMPORT BAŞLADI ===");

        // 🔥 ÖNEMLİ DÜZELTME BURADA!
        $url = html_entity_decode($this->config->get('module_xml_import_url'), ENT_QUOTES, 'UTF-8');
        $currency = $this->config->get('module_xml_import_currency');

        if (!$url) {
            $this->xml_import->log("HATA: XML URL yapılandırılmamış.");
            echo "XML URL tanımlı değil.";
            return;
        }

        if (!$currency) {
            $currency = 'USD';
        }

        // Kur çek
        $rate = $this->model_extension_module_xml_import->getRate($currency);
        $this->xml_import->log("Döviz türü: {$currency} | Kur: {$rate}");

        // XML indir
        $xmlPath = $this->xml_import->downloadXML($url, 'products.xml');

        if (!$xmlPath) {
            $this->xml_import->log("HATA: XML indirilemedi, import iptal.");
            echo "XML indirilemedi.";
            return;
        }

        // Ürünleri işle
        $this->model_extension_module_xml_import->importProducts($xmlPath, $rate);

        $this->xml_import->log("=== XML IMPORT BİTTİ ===");

        echo "OK";
    }
}
