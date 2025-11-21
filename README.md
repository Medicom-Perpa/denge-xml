# 🛒 OpenCart – Gelişmiş XML Ürün İçe Aktarma

(Cron + TCMB Döviz API'si + Çoklu Görsel Desteği)

Kısa: OpenCart 3.x için tasarlanmış, XML kaynaklı ürünleri çeken, döviz dönüşümü yapan, çoklu görselleri işleyen ve ürünleri güncelleyen stabil bir modül.

---

## 🚀 Öne Çıkan Özellikler
- Otomatik XML çekme ve eksik alan kontrolü  
- SKU bazlı güncelleme veya yeni ürün ekleme  
- TCMB tabanlı döviz dönüşümü (USD / EUR -> TL)  
- Dövizli fiyatlarda otomatik %10 kar marjı  
- Nokta / virgül format dönüşümleri  
- resim0…resim10 alanlarından çoklu görsel indirme ve product_image ekleme  
- Ayrıntılı loglama (günlük dosyaları + ana log)  
- 7 günden eski logların otomatik temizlenmesi  
- Cron ile tam otomasyon

---

## ⚙️ Hızlı Kurulum
1. Dosyaları OpenCart kök dizinine aynı klasör yapısıyla kopyalayın.  
2. Admin → Ek Modüller → "XML Import" modülünü aktif edin.  
3. XML URL’ini girin.  
4. Sunucunuza cron ekleyin:

```bash
# crontab örneği (günlük)
0 2 * * * wget -q -O - "https://site.com/index.php?route=extension/module/xml_import/cron"
```

---

## 🔧 Dosya Yapısı (kod olarak görünmesi için)
- `system/library/xml_import.php` — XML indirme, loglama  
- `catalog/model/extension/module/xml_import.php` — Ürün işleme mantığı  
- `catalog/controller/extension/module/xml_import.php` — Cron / manuel tetikleme  
- `storage/xml/` — İndirilen XML dosyaları  
- `storage/logs/` — Log dosyaları  
- `image/catalog/xml_import/` — İndirilen ürün görselleri  
- `api/tcmb/` — Yerel TCMB döviz API'si

Örnek dosya yolu kod bloğu:
```text
c:\inetpub\wwwroot\your-opencart\
|- system\library\xml_import.php
|- catalog\model\extension\module\xml_import.php
...
```

---

## ⚠️ ÖNEMLİ: TCMB API Linkini Düzenleyin
Modül, döviz dönüşümü için kendi sunucunuzdaki TCMB API'sini kullanır. Aşağıdaki satırı mutlaka kendi alan adınıza göre güncelleyin:

```php
$url = "https://seninsiten.com/api/tcmb/index.php?doviz=" . urlencode($currency);
```

Alan adını değiştirmezseniz döviz dönüşümü çalışmaz.

---

## 🔄 İşleyiş Özeti (adım adım)
1. Cron tetiklenir  
2. Ana log ve günlük log başlatılır  
3. XML cURL ile indirilir ve kaydedilir  
4. Fiyat cinsi kontrol edilir (USD / EUR / TL)  
5. USD/EUR ise TCMB kuru ile çarpılır ve %10 kar eklenir  
6. Görseller indirilir, `image/catalog/xml_import/` içine kaydedilir ve `product_image` tablosuna eklenir  
7. SKU kontrolü → ürün güncelle veya yeni ekle  
8. İşlem raporu / log saklanır

---

## 🛠️ İleri Seçenekler (isteğe bağlı)
- Kategori eşleştirme ve otomatik kategori oluşturma  
- Marka bazlı filtreleme  
- Stoksuz ürünleri otomatik pasif etme  
- Çoklu XML kaynağı desteği  
- Fiyat yuvarlama kuralları ve marka komisyonları

---

## 📝 Hata / Bakım & İpucu
- Loglar: `storage/logs/` ve günlük dosyalar — hataları buradan takip edin.  
- Görsel izinleri: `image/catalog/xml_import/` klasörünün yazılabilir olduğundan emin olun.  
- XML formatı değişirse eşlemeleri güncelleyin.  
- Gelişmiş hata takibi istiyorsanız `storage/logs/xml_import.log` dosyasını izleyin.

---

## 📮 İletişim / Geliştirme Talepleri
Yeni özellik talepleri veya entegrasyon istekleri için repo üzerinden issue açabilirsiniz.

--- 

Teşekkürler — yapılandırmayı doğru yaptığınızdan emin olun.