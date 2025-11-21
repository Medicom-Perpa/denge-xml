🛒 OpenCart – Advanced XML Product Importer

(Cron + TCMB Currency API + Multi-Image Support)

Bu repo, OpenCart 3.x için geliştirilmiş profesyonel, stabil ve yüksek performanslı bir
XML Ürün Entegrasyon Modülüdür.

Modül; XML kaynaklı ürünleri otomatik olarak çekmek, fiyatları TCMB döviz kuruna göre çevirmek, çoklu görselleri indirmek ve ürünleri otomatik güncellemek için tasarlanmıştır.

🚀 Özellikler
✔ XML’den otomatik ürün çekme

Ürünleri XML kaynağından alır

Eksik / boş XML kontrolü yapar

SKU’ya göre günceller veya yeni ürün ekler

✔ TCMB Döviz Kuru Entegrasyonu

XML’de fiyat cinsi okunur (USD / EUR / TL)

TL dışındaki fiyatlar senin sunucunda çalışan TCMB API ile anlık kurdan çevrilir

Çevrilen fiyatlara otomatik %10 kar eklenir

TL fiyatlar doğrudan işlenir

Nokta–virgül dönüşümleri otomatik yapılır

✔ Çoklu Görsel Desteği

XML içinde bulunan:

resim0, resim1, … resim10


alanlarının tamamını tarar ve indirir → product_image tablosuna ekler.

✔ Akıllı Loglama Sistemi

İşlemler storage/logs/xml_import.log dosyasına yazılır

Her import işleminde günlük (xml_import_YYYY-MM-DD.txt) logs tutulur

7 günden eski loglar otomatik temizlenir

Her importtan önce ana log sıfırlanır

✔ Cron ile Tam Otomasyon

Modül 24 saatte bir otomatik ürün güncellemeye uygundur.

Cron URL:

https://site.com/index.php?route=extension/module/xml_import/cron

⚠ ÖNEMLİ — TCMB API Linkinin Düzenlenmesi

Modül, döviz dönüşümü için senin kendi sunucunda çalışan TCMB API’sini kullanır.

Bu linki mutlaka kendi alan adınla değiştirmelisin:

https://seninsiten.com/api/tcmb/index.php?doviz=USD


Kodlarda şu satır bulunur:

$url = "https://seninsiten.com/api/tcmb/index.php?doviz=" . urlencode($currency);


👉 Eğer buradaki alan adını değiştirmezsen döviz kuru çalışmaz
👉 API tamamen lokal sunucunda barınır, dış API bağımlılığı yoktur
👉 Bu sistem fiyat hesaplamasını %100 stabil hale getirir

📂 Dosya Yapısı
/system/library/xml_import.php       → XML indirme + log sistemi
/catalog/model/extension/module/xml_import.php → Ürün işleme
/catalog/controller/extension/module/xml_import.php → Cron / manuel çalıştırma
/storage/xml/                        → XML dosyalarının indirildiği klasör
/storage/logs/                       → Log dosyaları
/image/catalog/xml_import/           → İndirilen ürün görselleri
/api/tcmb/                           → Senin kurduğun TCMB döviz API’si

🔧 Kurulum
1) Dosyaları aynı yapıyla OpenCart dizinine yükle
2) Admin → Ek Modüller → “XML Import” modülünü aktif edin
3) XML URL’ini girin
4) Cron ayarlarını ekleyin
0 */24 * * * wget -q -O - "https://site.com/index.php?route=extension/module/xml_import/cron"

🔄 Çalışma Mantığı

Cron tetiklenir

Ana log sıfırlanır

XML cURL ile indirilir

Fiyat cinsi kontrol edilir

USD/EUR → TCMB kuru ile çarpılır

TL → doğrudan fiyat

Tüm fiyatlara %10 eklenir

Görseller indirilir

Ürün var mı kontrol edilir

Güncelleme veya ekleme yapılır

Loglara yazılır

🎯 Proje Felsefesi

Bu modül şunları hedefler:

%100 otomatik çalışan ürün senkronizasyonu

Stabil ve güvenli döviz dönüşümü

Çoklu görsel destekli yüksek kaliteli ürün import

OpenCart çekirdeğine dokunmadan maksimum uyumluluk

Tamamen geliştirici dostu, modüler yapı

📮 Ek Özellik Talepleri İçin

İstersen şu ek özellikleri de sisteme entegre edebilirim:

Kategori eşleştirme

Markaya göre filtreleme

Stoksuz ürünleri otomatik pasif etme

Çoklu tedarikçi / çoklu XML desteği

Otomatik fiyat yuvarlama

Belirli markalara özel komisyon sistemi