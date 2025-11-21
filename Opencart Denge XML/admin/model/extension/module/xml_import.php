<?php
class ModelExtensionModuleXmlImport extends Model {

    /**
     * MODÜL AYARLARINI GETİR
     */
    public function getSettings() {
        return $this->config->get('module_xml_import');
    }

    /**
     * MODÜL AYARLARINI KAYDET
     * Ürün XML URL
     * Kategori XML URL
     * Döviz tipi
     * Modül status
     */
    public function saveSettings($data) {

        // Admin → controller → POST gelen her şey burada kaydedilir
        $this->load->model('setting/setting');

        // module_xml_import_* ile başlayan tüm alanlar kaydedilir
        $this->model_setting_setting->editSetting('module_xml_import', $data);
    }

    /**
     * ŞİMDİ ÇALIŞTIR (Admin’den tetiklenir)
     * Catalog tarafındaki run_now fonksiyonunu çağırır
     */
    public function runNow() {

        // Catalog URL belirleniyor
        if (defined('HTTPS_CATALOG')) {
            $url = HTTPS_CATALOG . 'index.php?route=extension/module/xml_import/run_now';
        } else {
            $url = HTTP_CATALOG . 'index.php?route=extension/module/xml_import/run_now';
        }

        // cURL ile çalıştır
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 90);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($curl);

        if (curl_errno($curl)) {
            $error_msg = curl_error($curl);
            curl_close($curl);
            return "HATA: " . $error_msg;
        }

        curl_close($curl);

        return $response ? $response : "Boş yanıt döndü.";
    }
}
