# Yükseltme öncesi davranış testleri

Bu testler PHP 8.5 geçiş planının ilk maddesinde mevcut davranışı sabitlemek için eklendi. İlk doğrulama sırasında uygulama kodu ve Composer bağımlılıkları değiştirilmedi. Sonraki aşamaların güncel sonuçları aşağıda ve [geçiş planında](../PHP85-UPGRADE-PLAN.md) yer alıyor.

## Çalıştırma

Tüm testleri geçici PostgreSQL ortamıyla çalıştırmak için:

```sh
composer install --prefer-dist --no-interaction
composer test
```

Gerekenler: PHP 8.5 CLI, Composer gereksinimlerini karşılayan eklentiler, GD, Redis, PDO PostgreSQL, Argon2id desteği ve PostgreSQL'in `initdb` / `pg_ctl` araçları. `composer test`, `test/run-characterization-tests.sh` çalıştırıcısını kullanır. Çalıştırıcı PostgreSQL araçlarını `pg_config --bindir` üzerinden bulur. Gerekirse dizin açıkça belirtilebilir:

```sh
PHP85_TEST_POSTGRES_BIN=/opt/homebrew/opt/postgresql@15/bin bash test/run-characterization-tests.sh
```

Çalıştırıcı `/tmp` altında yeni bir PostgreSQL kümesi oluşturur. TCP dinlemesi kapalıdır; bağlantı yalnızca o çalıştırmaya ait özel Unix socket üzerinden kurulur. Her veritabanı testi kendi transaction'ını geri alır. Çalıştırıcı tamamlanınca veritabanını durdurur ve geçici dizini siler. Mevcut PostgreSQL veya Redis sunucularına bağlanılmaz.

Yalnızca harici servis gerektirmeyen testleri çalıştırmak için:

```sh
composer test-unit
```

PostgreSQL çalıştırıcısı kullanılmadan tüm testler çağrılırsa PostgreSQL testleri açık bir açıklamayla atlanır ve sıkı PHPUnit ayarları nedeniyle komut başarısız olur. Veritabanı testleri, çalıştırıcının oluşturduğu socket ve işaret dosyası olmadan bağlantı açmaz.

PHPUnit seçenekleri Composer üzerinden iletilebilir:

```sh
composer test -- --colors=never --filter OrmPersistenceTest
```

Coverage için PCOV veya Xdebug coverage desteği gerekir. Aşağıdaki komut aynı izole PostgreSQL ortamında tüm testleri çalıştırır ve proje kökünde `clover.xml` üretir. Normal test komutu coverage raporu üretmez.

```sh
composer test-coverage
```

## Eklenen kapsam

| Alan | Yeni test sayısı | Doğrulanan davranış |
| --- | ---: | --- |
| JWT ve oturum | 13 | HS256 imzası, issuer, kullanıcı ve rol claim'leri, 12 saatlik süre, yanıt biçimi, hatalı parola, giriş limiti, süresi geçmiş veya henüz geçerli olmayan token'lar, middleware izin aktarımı ve mevcut herkese açık yollar. |
| Payload temizleme | 6 | Boş payload, tag ve NUL temizleme, anahtar ve değerlerde tırnak davranışı, Türkçe karakterler, iç içe diziler, scalar tipler, email temizleme, Argon2id hash'i ve yola özel HTML izinleri. |
| Doctrine / PostgreSQL | 17 | Gerçek sorgularla DTO dönüşümü, snake_case alan eşlemesi, JSON metadata, büyük/küçük harften bağımsız email araması, silinmiş kayıtlar, sıralama, integer sayfalama parametreleri, boş sonuçlar ve parent kapsamı. |
| RedisJSON | 5 | Uygulamanın `ReJSONFactory` bağlantısı, JSON serileştirme, object/scalar dönüşümü, eksik kayıt, çoklu okuma sırası ve path silme. |
| Görsel yükleme | 5 | Gerçek GD ve Flysystem ile PNG/JPEG, ham ve Base64 içerik, iki upload handler'ı, varsayılan boyutlar, en-boy oranı, özel metadata, dosya taşıma, yanıt yolu ve içerik düzenleme izni. |

## Kapsamın sınırları

- RedisJSON testleri gerçek factory ve kütüphaneyi kullanır; yalnızca native Redis istemcisinin komut taşıma katmanı mock edilir. RedisJSON sunucu modülü çalıştırılmaz.
- Görsel testleri gerçek dosya ve boyutlandırma işlemlerini geçici bir çalışma dizininde yürütür. Veritabanına dosya kaydı yazan repository mock edilir; kaydedilmek üzere iletilen metadata doğrulanır.
- PostgreSQL testleri DBAL sorgularını ve eklenen ORM yollarını çalıştırır. Sekiz entity'nin mapping'i doğrulanır; kullanıcı CRUD işlemleri, collection JSON hydration, JSON DQL, cache ve proxy davranışı test edilir. Tüm entity'lerin tüm persist işlemleri kapsanmaz.
- Mevcut JWT middleware'i token imzasını doğrulamıyor. Testler geçersiz imzanın kabul edilmesini korunması gereken davranış olarak sabitlemez. Üretilen token'ın imzası bağımsız HMAC hesabıyla doğrulanır; middleware imza doğrulaması ayrı bir düzeltme olarak ele alınmalıdır.
- İlk aşamada PHP 8.5 deprecation uyarıları ve eski PHPUnit XML şeması değiştirilmedi. O aşamada PHP_CodeSniffer 3.7.1 kendi kodundaki null array offset nedeniyle kontrolü tamamlayamıyordu; 2. maddede 3.13.6 sürümüne güncellendi. Üçüncü maddede uygulama deprecation uyarıları, 5. maddede Doctrine DBAL uyarıları, 6. maddede PHPUnit XML ve PSR-4 uyarıları giderildi.

## İlk doğrulama sonucu

21 Ekim 2026 tarihinde PHP 8.5.6, PHPUnit 9.5.27 ve izole PostgreSQL 15 ortamında **80 test, 243 assertion** başarılı oldu; test atlanmadı. Önceki 34 teste 46 davranış testi eklendi.

## PHP 8.5 uyumluluk regresyonları

Planın 3. maddesinde 22 test vakası daha eklendi: metin filtresinin 18 sınır durumu, koleksiyonların iterasyon/serileştirme/boş sonuç davranışı ve exception oluşturma. O aşamada vergi numarası, payload, exception ve koleksiyon testleri `PhpDeprecationTestCase` üzerinden PHP `E_DEPRECATED` uyarılarını test hatasına çeviriyordu. Altıncı maddede bu özel taban kaldırıldı; tüm testlerde PHPUnit'in hata işleyicisi ve sıkı XML ayarları kullanılıyor. Koleksiyon testleri sınıf yükleme uyarılarını da yakalamak için ayrı PHP süreçlerinde çalışır.

Yalnızca bu uyumluluk testlerini çalıştırmak için:

```sh
php -d error_reporting=-1 vendor/bin/phpunit --no-coverage --no-logging --do-not-cache-result \
  --filter '(PayloadSanitizerTest|PrivateTaxIdTest|CorporateTaxIdTest|DomainExceptionTest|ResultObjectsCollectionTest)'
```

Üçüncü maddenin sonunda PHP 8.5.6 ve PHPUnit 9.6.38 ile **36 test, 56 assertion** başarılı oldu. O aşamada tüm testler izole PostgreSQL 15 ile ve ilk hatada durmayı kapatan geçici XML yapılandırmasıyla çalıştırıldığında **102 test, 195 assertion, 7 hata ve 2 başarısızlık** vardı; test atlanmadı. Bu 9 vaka, 4. maddede giderilen JWT, Flysystem ve RedisJSON API farklarına aitti.

## Kütüphane API geçişleri sonrası doğrulama

Planın 4. maddesi tamamlandı. PHP 8.5.6, PHPUnit 9.6.38 ve izole PostgreSQL 15 ile **124 test, 379 assertion başarılı; test atlanmadı**. Çalıştırma komutu:

```sh
bash test/run-characterization-tests.sh --colors=never
```

Bu adımda 22 vaka eklendi: 7 geçersiz JWT claim'i, 3 ek görsel girdisi, 10 RedisJSON sınır durumu, 1 yerel depolama/dizin izni ve 1 içerik slug testi. Önceki testlerin davranış beklentileri korunuyor; Flysystem dizin listesi yeni API'nin `toArray()` metodu üzerinden aynı boş diziyle karşılaştırılıyor.

- Oturum testi HS256 imzasını bağımsız HMAC hesabıyla, integer zaman claim'lerini, 12 saatlik süreyi ve üretilen token'ın middleware'den geçişini doğruluyor. Yeni nullable claim API'sine karşı eksik, boş ve yanlış tipte kullanıcı/rol değerleri de reddediliyor.
- Görsel testleri ham/Base64 JPEG ve PNG, dikey görseller, büyütme, en-boy oranı, PNG şeffaflığı, dosya taşıma, metadata ve yanıt yollarını gerçek GD/Flysystem işlemleriyle doğruluyor. Yerel factory testi yapılandırılmış kökü ve eski `0755` dizin izinlerini kontrol ediyor.
- RedisJSON testleri factory üzerinden kullanılan uyumluluk adaptöründe `stdClass`, boş nesne/dizi ayrımı, sayısal nesne alanları, scalar değerler, eksik/tekrarlı `MGET` sonuçları, format seçenekleri ve çoklu path sonuçlarını doğruluyor. Yalnızca native Redis komut taşıma katmanı mock ediliyor; gerçek RedisJSON sunucu modülü kullanılmıyor.
- UUID shortener testi gerçek `ContentRepository` çıktısındaki Türkçe başlık ve UUID'den üretilen public slug'ın değişmediğini doğruluyor; bu testte veritabanı sonucu mock ediliyor.

API geçişlerine odaklanan testler için:

```sh
php -d error_reporting=-1 vendor/bin/phpunit --no-coverage --no-logging --do-not-cache-result \
  --filter '(StartSessionTest|PrivateApiAuthorizationMiddlewareTest|ImageUploadTest|ReJSONFactoryTest|LocalFactoryTest|ContentSlugCompatibilityTest)'
```

Bu komut 4. madde sonunda **45 test ve 229 assertion** ile başarılı oldu. O aşamada testlerde uygulama kaynak kodundan PHP deprecation uyarısı gelmedi; Doctrine DBAL ve eski PHPUnit XML şeması uyarıları vardı. Yukarıda belgelenen middleware imza doğrulaması eksikliği bu API uyarlamasıyla değiştirilmedi.

## Doctrine uyumluluğu sonrası sonuç

Planın 5. maddesinde DBAL **2.13.9** ve ORM **2.20.13** korundu. DBAL'in iki dosyasındaki PHP 8.5 uyumsuzluğu, Composer kurulumunda otomatik uygulanan [yerel yamayla](../patches/README.md) giderildi. ORM factory'sinde entity dizini, tekrarlanan UUID kaydı ve cache havuzları düzeltildi.

16 yeni vaka eklendi: 4 PostgreSQL bağlantı/prepare, 3 factory, 7 ORM işlem ve 2 boolean platform testi. Beşinci maddede `PostgresTestCase` de PHP deprecation test tabanına taşındı; altıncı maddede tüm testler ortak PHPUnit hata işleyicisine geçti. Veritabanı transaction'ı setup veya test hatasında da temizlenir.

- Sekiz entity'nin annotation mapping'i iki cache profilinde keşfedilip doğrulanır.
- Native PDO prepare seçenekleri, varsayılan ayar, yeni/eski PostgreSQL sürücü sınıfları ve boolean hata mesajı korunur.
- Kullanıcı ekleme, yeniden okuma, güncelleme ve silme; UUID/tarih dönüşleri, collection JSON alanları ve nullable parent doğrulanır.
- `JSON_GET` / `JSON_GET_TEXT` DQL ve proxy başlatma, klonlama, serileştirme geliştirme/üretim cache profillerinde çalıştırılır.

PHP 8.5.6 ve izole PostgreSQL 15 ile hem projedeki kurulumda hem boş vendor'dan yapılan temiz Composer kurulumunda **140 test, 443 assertion başarılı; hata veya atlanan test yok**. Testlerde proje veya Doctrine kaynaklı PHP deprecation uyarısı yok. PHPUnit'in eski XML şeması uyarısı planın 6. maddesine bırakıldı.

```sh
bash test/run-characterization-tests.sh --colors=never
```

Yalnızca yeni Doctrine kontrolleri için:

```sh
bash test/run-characterization-tests.sh --colors=never \
  --filter '(ConnectionCompatibilityTest|EntityManagerFactoryTest|OrmPersistenceTest|PostgreSqlPlatformCompatibilityTest)'
```

DBAL 2 resmî bakım kapsamı dışındadır; yerel yamanın bakımı projeye aittir. Bu testler PostgreSQL ve belirtilen ORM yollarını kapsar. Diğer DBAL sürücüleri ve tüm entity yazma yolları için genel bir uyumluluk sonucu çıkarılmaz.

## Test altyapısı sonrası güncel sonuç

Planın 6. maddesi tamamlandı. **PHP 8.5.6, PHPUnit 12.5.40 ve izole PostgreSQL 15 ile 140 test, 510 assertion başarılı; hata, warning, notice, deprecation, risky veya atlanan test yok.** Aynı sonuç boş `vendor/` ile temiz Composer kurulumunda ve PCOV 1.0.12 ile coverage çalıştırmasında da alındı. Clover XML raporu 192 kaynak dosyası içeriyor; coverage kapsamı `src/BackendBase` dizini.

- Eski 34 `@test` işareti `#[Test]`, 11 data provider kullanımı `#[DataProvider]` oldu. Süreç izolasyonu ve global state metadata'sı da attribute kullanıyor. Önceki 140 vakanın adları ve veri kümeleri yeni keşif listesiyle birebir eşleşiyor.
- `phpunit.xml.dist` kurulu PHPUnit 12.5 XSD'siyle doğrulanıyor. Tüm issue türleri ve boş test paketi başarısız sonuç verir. `@` ile bastırılmış PHP deprecation uyarıları da raporlanır; baseline veya deprecation dışlaması yok. Bu davranış geçici hata üreten testlerle ayrıca kontrol edildi.
- PHPUnit'in yeni mock kontrollerine göre yalnızca bağımlılık sağlamak için kullanılan nesneler stub'a çevrildi. Kullanıcı aramasına bir çağrı beklentisi eklendi. Assertion toplamındaki farkın geri kalanı PHPUnit sürümlerinin sayım farkından gelir; davranış vakaları azaltılmadı.
- PHPUnit için gerekli parser geçişinde PHP Assumptions 0.9.1, PHP Parser 5.9.0 ve Brick VarExporter 0.7.0 kuruldu. Gerçek ConfigAggregator cache yazma/okuma, closure değerinin korunması ve PHP Assumptions parser çalışması ayrıca doğrulandı.
- Geliştirme bağımlılıkları açık ve `--no-dev` temiz Composer kurulumları başarılı. DBAL yaması her ikisinde otomatik uygulandı; Composer girdileri değişmedi. `composer validate --strict --no-check-publish`, platform koşulları ve optimize PSR-4 kontrolü başarılı. Güvenlik duyurusu bulunmadı.

Kurulumdaki üç bakım uyarısı sürüyor: `doctrine/annotations`, `doctrine/cache`, `selami/stdlib` abandoned. İlk ikisi korunan Doctrine 2 hattında; sonuncusu mevcut yardımcı sınıflarda kullanılıyor. Bu paketlerin değiştirilmesi ayrı bir geçiş gerektirir; uyarılar gizlenmedi.

Yerel CLI'da gereken PHP eklentileri, PDO PostgreSQL, GD, Redis, sodium ve Argon2id doğrulandı. `openswoole` 26.2.0 yüklü; `swoole` eklentisi ve `mezzio/mezzio-swoole` paketi yok. Mevcut Swoole provider bu ortamda boş yapılandırma döndürüyor; gerçek Swoole sunucusu veya üretim ortamı doğrulanmış sayılmaz. Gerçek RedisJSON sunucu modülünün çalıştırılmadığına ilişkin yukarıdaki sınır da geçerlidir.
