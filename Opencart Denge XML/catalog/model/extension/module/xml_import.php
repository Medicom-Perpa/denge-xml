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

        if ($currency == 'TL' || $currency == 'TRY' || $currency == '') {
            return 1;
        }

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

            // ✔ DOĞRU KATEGORİ KODU
            $category_code = trim((string)$p->katmannumarsi);

            //--------------------------------------
            // TÜM RESİMLERİ TOPLA
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

            $image_main = isset($downloaded_images[0]) ? $downloaded_images[0] : '';
            $image_db   = $this->db->escape($image_main);

            //---------------------------
            // FİYAT HESAPLAMA
            //---------------------------
            $price_raw = str_replace(',', '.', $price_raw);
            $price = (float)$price_raw;

            $currency = strtoupper(trim($currency));

            if ($currency == '' || $currency == 'TL' || $currency == 'TRY') {
                $price_tl = $price;
            } else {
                $rate = $this->getRate($currency);
                $price_tl = $price * $rate;
            }

            // +%10 kar
            $price_tl = $price_tl * 1.10;
            $price_tl = number_format($price_tl, 2, '.', '');

            //---------------------------
            // SKU boşsa işlem yok
            //---------------------------
            if ($sku === "") {
                $this->xml_import->log("ATLANDI: SKU boş.");
                continue;
            }

            $sku_db     = $this->db->escape($sku);
            $name_db    = $this->db->escape($name);
            $model_db   = $this->db->escape($model);
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

            /**
             * ------------------------------------------
             * ÜRÜN → KATEGORİ BAĞLAMA
             * ------------------------------------------
             */
            if ($category_code != "") {

                $category_id = $this->getCategoryIdByDengeCode($category_code);

                if ($category_id) {

                    $this->db->query("DELETE FROM " . DB_PREFIX . "product_to_category WHERE product_id = " . (int)$product_id);

                    $this->db->query("
                        INSERT INTO " . DB_PREFIX . "product_to_category
                        SET product_id = '" . (int)$product_id . "',
                            category_id = '" . (int)$category_id . "'
                    ");

                    $this->xml_import->log("Kategori bağlandı → Product: {$product_id}, KatKod: {$category_code}, KatID: {$category_id}");

                } else {
                    $this->xml_import->log("UYARI: Kategori bulunamadı → Kod: {$category_code}, SKU: {$sku}");
                }
            }
        }

        $this->xml_import->log("Toplam Yeni: {$total_insert}");
        $this->xml_import->log("Toplam Güncelleme: {$total_update}");
        $this->xml_import->log("XML import tamamlandı.");

        return true;
    }


    /**
     * KATEGORİ IMPORT
     */
    public function importCategories($xmlPath) {

        $this->load->library('xml_import');
        $this->xml_import->log("Kategori XML işleme başladı: " . $xmlPath);

        if (!file_exists($xmlPath)) {
            $this->xml_import->log("HATA: Kategori XML dosyası bulunamadı: " . $xmlPath);
            return false;
        }

        $xml = simplexml_load_file($xmlPath);
        if (!$xml) {
            $this->xml_import->log("HATA: Kategori XML parse edilemedi.");
            return false;
        }

        $language_id = (int)$this->config->get('config_language_id');

        $total_parent = 0;
        $total_child  = 0;

        // ✔ DENGE XML'de <grup> kullanılıyor
        foreach ($xml->grup as $g) {

            $parent_name = trim((string)$g->grupadi);
            $parent_code = trim((string)$g->grupkodu);

            $child_name  = trim((string)$g->katman);
            $child_code  = trim((string)$g->katmannumarsi);

            if (!$parent_name || !$parent_code || !$child_name || !$child_code) {
                $this->xml_import->log("Uyarı: Eksik kategori satırı atlandı.");
                continue;
            }

            // Parent
            $parent_id = $this->getOrCreateDengeCategory($parent_code, $parent_name, 0, $language_id);
            $total_parent++;

            // Child
            $child_id  = $this->getOrCreateDengeCategory($child_code, $child_name, $parent_id, $language_id);
            $total_child++;
        }

        $this->xml_import->log("Kategori import tamamlandı. Parent: {$total_parent}, Child: {$total_child}");
        return true;
    }


    /**
     * Kategori oluşturucu
     */
    private function getOrCreateDengeCategory($code, $name, $parent_id, $language_id) {

        $query = $this->db->query("
            SELECT category_id FROM " 
            . DB_PREFIX . "denge_category_map 
            WHERE denge_code = '" . $this->db->escape($code) . "'
        ");

        if ($query->num_rows) {
            return (int)$query->row['category_id'];
        }

        // Yeni kategori ekle
        $this->db->query("INSERT INTO " . DB_PREFIX . "category SET 
            parent_id   = " . (int)$parent_id . ",
            `top`       = '" . ($parent_id ? 0 : 1) . "',
            `column`    = 1,
            sort_order  = 0,
            status      = 1,
            date_added  = NOW(),
            date_modified = NOW()
        ");

        $category_id = (int)$this->db->getLastId();

        // Description
        $this->db->query("INSERT INTO " . DB_PREFIX . "category_description SET
            category_id = " . (int)$category_id . ",
            language_id = " . (int)$language_id . ",
            `name`      = '" . $this->db->escape($name) . "',
            meta_title  = '" . $this->db->escape($name) . "'
        ");

        // Store
        $this->db->query("INSERT INTO " . DB_PREFIX . "category_to_store SET
            category_id = " . (int)$category_id . ",
            store_id    = 0
        ");

        // SEO
        $keyword = $this->slugify($name);

        if ($keyword) {

            $base = $keyword;
            $i = 1;

            while (true) {
                $seo_q = $this->db->query("SELECT seo_url_id FROM " . DB_PREFIX . "seo_url WHERE keyword = '" . $this->db->escape($keyword) . "'");
                if (!$seo_q->num_rows) break;

                $keyword = $base . '-' . $i++;
            }

            $this->db->query("INSERT INTO " . DB_PREFIX . "seo_url SET
                store_id   = 0,
                language_id= " . (int)$language_id . ",
                `query`    = 'category_id=" . (int)$category_id . "',
                keyword    = '" . $this->db->escape($keyword) . "'
            ");
        }

        // MAP TABLOSU
        $this->db->query("INSERT INTO " . DB_PREFIX . "denge_category_map SET
            denge_code = '" . $this->db->escape($code) . "',
            category_id = " . (int)$category_id . "
        ");

        $this->xml_import->log("Yeni kategori oluşturuldu: {$name} (code: {$code}, id: {$category_id}, parent: {$parent_id})");

        return $category_id;
    }


    /**
     * Kod → kategori_id getir
     */
    public function getCategoryIdByDengeCode($code) {

        $query = $this->db->query("
            SELECT category_id FROM " 
            . DB_PREFIX . "denge_category_map 
            WHERE denge_code = '" . $this->db->escape($code) . "'
        ");

        if ($query->num_rows) {
            return (int)$query->row['category_id'];
        }

        return 0;
    }


    /**
     * Türkçe destekli slugify
     */
    private function slugify($text) {

        $text = trim($text);

        $map = [
            'ş'=>'s','Ş'=>'s','ı'=>'i','İ'=>'i','ç'=>'c','Ç'=>'c',
            'ü'=>'u','Ü'=>'u','ö'=>'o','Ö'=>'o','ğ'=>'g','Ğ'=>'g'
        ];

        $text = strtr($text, $map);
        $text = preg_replace('~[^\\pL\\d]+~u', '-', $text);
        $text = trim($text, '-');
        $text = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
        $text = strtolower($text);
        $text = preg_replace('~[^-a-z0-9]+~', '', $text);

        return $text ?: 'kategori';
    }

}
