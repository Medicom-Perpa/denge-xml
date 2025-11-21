<?php
class ModelExtensionModuleXmlImport extends Model {

    /**
     * GÖRSEL İNDİRME FONKSİYONU
     */
    private function downloadImage($url) {
        if (!$url) return '';

        $ext = pathinfo($url, PATHINFO_EXTENSION);
        $ext = strtolower($ext);

        if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
            $ext = 'jpg';
        }

        $filename = 'xml_' . md5($url) . '.' . $ext;

        $folder = DIR_IMAGE . 'catalog/xml_import/';
        $savePath = $folder . $filename;

        if (!is_dir($folder)) {
            mkdir($folder, 0777, true);
        }

        if (!file_exists($savePath)) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0");

            $img = curl_exec($ch);
            curl_close($ch);

            if ($img) {
                file_put_contents($savePath, $img);
            }
        }

        return 'catalog/xml_import/' . $filename;
    }


    /**
     * KUR ALMA
     */
    public function getRate($currency) {

        $currency = strtoupper(trim($currency));

        // XML'deki TL → TRY
        if ($currency == 'TL' || $currency == 'TRY' || $currency == '') {
            return 1;
        }

        // TCMB API (sende kurulu olan)
        $url = "https://iconsepeti.medicombilgisayar.com/api/tcmb/index.php?doviz=" . urlencode($currency);

        $json = @file_get_contents($url);

        if (!$json) {
            $this->xml_import->log("HATA: Döviz API bağlantı hatası. URL: $url");
            return 1;
        }

        $data = json_decode($json, true);

        if (!isset($data["Satis"])) {
            $this->xml_import->log("HATA: Döviz API yanıtı geçersiz. Veri: " . $json);
            return 1;
        }

        return (float)$data["Satis"];
    }

    /**
     * ÜRÜN IMPORT
     */
    public function importProducts($xmlPath, $defaultCurrency = 'USD') {

        $this->load->library('xml_import');
        $this->xml_import->log("XML işleme başladı.");

        if (!file_exists($xmlPath)) {
            $this->xml_import->log("HATA: XML dosyası bulunamadı: " . $xmlPath);
            return false;
        }

        $xml = simplexml_load_file($xmlPath);

        $total_insert = 0;
        $total_update = 0;

        foreach ($xml->urun as $p) {

            // XML alanları
            $sku      = trim((string)$p->urunKodu1);
            $model    = trim((string)$p->ureticikodu);
            $name     = trim((string)$p->urunAdi);
            $price_raw= trim((string)$p->fiyat1);
            $currency = trim((string)$p->fiyatcinsi1);
            $stock    = (int)$p->stok;
            $brand    = trim((string)$p->marka);
            $barcode  = trim((string)$p->barkod);

            //--------------------------------------
            // TÜM RESİMLERİ TOPLA (resim0 → resim10)
            //--------------------------------------
            $image_list = [];

            for ($i = 0; $i <= 10; $i++) {
                $tag = "resim{$i}";
                if (isset($p->{$tag}) && trim((string)$p->{$tag}) !== "") {
                    $image_list[] = trim((string)$p->{$tag});
                }
            }

            //---------------------------
            // GÖRSELLERİ İNDİR
            //---------------------------
            $downloaded_images = [];

            foreach ($image_list as $image_url) {
                $local = $this->downloadImage($image_url);
                if ($local) {
                    $downloaded_images[] = $local;
                }
            }

            // Ana görsel
            $image_main = isset($downloaded_images[0]) ? $downloaded_images[0] : '';
            $image_db   = $this->db->escape($image_main);


            //---------------------------
            // FİYAT HESAPLAMA SİSTEMİ
            //---------------------------

            // 1) Virgül → nokta düzelt
            $price_raw = str_replace(',', '.', $price_raw);
            $price = (float)$price_raw;

            // 2) Döviz cinsini normalize et
            $currency = strtoupper(trim($currency));

            // 3) TL → direkt
            if ($currency == '' || $currency == 'TL' || $currency == 'TRY') {
                $price_tl = $price;

            // 4) USD/EUR → döviz kuru ile çarp
            } else {
                $rate = $this->getRate($currency);
                $price_tl = $price * $rate;
            }

            // 5) Hepsine %10 ekle
            $price_tl = $price_tl * 1.10;

            // 6) Number format
            $price_tl = number_format($price_tl, 2, '.', '');


            //---------------------------
            // SKU boşsa atla
            //---------------------------
            if ($sku === "") {
                $this->xml_import->log("ATLANDI: SKU boş.");
                continue;
            }

            // Escape
            $sku_db     = $this->db->escape($sku);
            $name_db    = $this->db->escape($name);
            $model_db   = $this->db->escape($model);
            $brand_db   = $this->db->escape($brand);
            $barcode_db = $this->db->escape($barcode);

            //---------------------------
            // ÜRÜN VAR MI?
            //---------------------------
            $q = $this->db->query("
                SELECT product_id FROM " . DB_PREFIX . "product 
                WHERE sku = '" . $sku_db . "'
            ");

            if ($q->num_rows) {

                // --------- GÜNCELLEME ---------
                $product_id = $q->row['product_id'];

                $this->db->query("
                    UPDATE " . DB_PREFIX . "product 
                    SET model = '" . $model_db . "',
                        price = '" . (float)$price_tl . "',
                        quantity = '" . (int)$stock . "',
                        image = '" . $image_db . "',
                        upc = '" . $barcode_db . "',
                        date_modified = NOW()
                    WHERE product_id = '" . (int)$product_id . "'
                ");

                $this->db->query("
                    UPDATE " . DB_PREFIX . "product_description 
                    SET name = '" . $name_db . "'
                    WHERE product_id = '" . (int)$product_id . "'
                ");

                //---------------------------
                // EK GÖRSELLERİ TEMİZLE + EKLE
                //---------------------------
                $this->db->query("DELETE FROM " . DB_PREFIX . "product_image WHERE product_id = '" . (int)$product_id . "'");

                if (count($downloaded_images) > 1) {
                    $sort = 0;
                    foreach ($downloaded_images as $idx => $local_path) {
                        if ($idx == 0) continue;

                        $this->db->query("
                            INSERT INTO " . DB_PREFIX . "product_image
                            SET product_id = '" . (int)$product_id . "',
                                image = '" . $this->db->escape($local_path) . "',
                                sort_order = '" . (int)$sort . "'
                        ");

                        $sort++;
                    }
                }

                $total_update++;
                $this->xml_import->log("GÜNCELLENDİ → SKU: {$sku} | TL: {$price_tl}");

            } else {

                // --------- EKLEME ---------
                $this->db->query("
                    INSERT INTO " . DB_PREFIX . "product
                    SET sku = '" . $sku_db . "',
                        model = '" . $model_db . "',
                        price = '" . (float)$price_tl . "',
                        quantity = '" . (int)$stock . "',
                        image = '" . $image_db . "',
                        status = 1,
                        upc = '" . $barcode_db . "',
                        date_added = NOW()
                ");

                $product_id = $this->db->getLastId();

                $this->db->query("
                    INSERT INTO " . DB_PREFIX . "product_description
                    SET product_id = '" . (int)$product_id . "',
                        name = '" . $name_db . "',
                        language_id = 1
                ");

                //---------------------------
                // EK GÖRSELLERİ EKLE
                //---------------------------
                if (count($downloaded_images) > 1) {
                    $sort = 0;
                    foreach ($downloaded_images as $idx => $local_path) {
                        if ($idx == 0) continue;

                        $this->db->query("
                            INSERT INTO " . DB_PREFIX . "product_image
                            SET product_id = '" . (int)$product_id . "',
                                image = '" . $this->db->escape($local_path) . "',
                                sort_order = '" . (int)$sort . "'
                        ");

                        $sort++;
                    }
                }

                $total_insert++;
                $this->xml_import->log("EKLENDİ → SKU: {$sku} | TL: {$price_tl}");
            }
        }

        $this->xml_import->log("Toplam Yeni: {$total_insert}");
        $this->xml_import->log("Toplam Güncelleme: {$total_update}");
        $this->xml_import->log("XML import tamamlandı.");

        return true;
    }
}
