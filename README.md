# TurkNews Twitter Auto

WordPress yazılarını otomatik olarak Twitter'da paylaşan eklenti.

## Özellikler

- WordPress yazıları otomatik tweet olarak paylaşma
- Öne çıkan görseli tweet'e ekleme
- Özelleştirilebilir tweet şablonları
- Kategori bazlı hashtag otomatik ekleme
- Debug modu
- WordPress admin paneli entegrasyonu

## Kurulum

1. Eklentiyi WordPress eklentiler klasörüne yükleyin
2. WordPress admin panelinden eklentiyi etkinleştirin
3. "TurkNews Twitter" menüsünden ayarları yapılandırın

## Ayarlar

- **Twitter Auth Token**: Twitter hesabınızın auth token'ı
- **Twitter CSRF Token**: Twitter hesabınızın csrf token'ı
- **Otomatik Tweet**: Yeni yazılar için otomatik tweet açık/kapalı
- **Tweet Şablonu**: Tweet metni şablonu ({title}, {link}, {category} değişkenlerini kullanabilirsiniz)
- **Görsel Ekle**: Öne çıkan görseli tweet'e ekleme açık/kapalı
- **Debug Modu**: Hata ayıklama modu açık/kapalı

## Kullanım

1. Twitter hesabınızın auth token ve csrf token bilgilerini alın
2. Ayarlar sayfasında bu bilgileri girin
3. Tweet şablonunu özelleştirin
4. Yeni bir yazı yayınladığınızda otomatik olarak tweet atılacaktır

## Güvenlik

- Tüm API istekleri SSL üzerinden yapılır
- Token bilgileri WordPress veritabanında şifrelenmiş olarak saklanır
- Rate limiting kontrolü yapılır

## Destek

Sorularınız için: info@turknews.co.uk 