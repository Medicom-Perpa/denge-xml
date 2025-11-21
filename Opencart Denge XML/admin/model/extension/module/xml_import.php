<?php
class ModelExtensionModuleXmlImport extends Model {

    // Modül ayarlarını al
    public function getSettings() {
        return $this->config->get('module_xml_import');
    }

    // Modül ayarlarını kaydet
    public function saveSettings($data) {
        $this->load->model('setting/setting');
        $this->model_setting_setting->editSetting('module_xml_import', $data);
    }

    // Admin panelden “Şimdi Çalıştır” komutu gönder
    public function runNow() {

        // Catalog tarafında çalışacak URL
        if (defined('HTTPS_CATALOG')) {
            $url = HTTPS_CATALOG . 'index.php?route=extension/module/xml_import/run_now';
        } else {
            $url = HTTP_CATALOG . 'index.php?route=extension/module/xml_import/run_now';
        }

        // cURL ile sunucuya istek at
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 60);

        $response = curl_exec($curl);

        if (curl_errno($curl)) {
            $error_msg = curl_error($curl);
            curl_close($curl);
            return "HATA: " . $error_msg;
        }

        curl_close($curl);

        return $response;
    }
}
