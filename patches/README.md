# Doctrine DBAL 2 / PHP 8.5 uyumluluk yaması

`doctrine-dbal-2.13.9-php85.patch`, DBAL 2.13.9 ve mevcut sorgu/DTO okuma API'lerini korumak için iki dosyayı düzeltir:

- PostgreSQL PDO sürücüsünde `PDO::PGSQL_ATTR_DISABLE_PREPARES` yerine PHP 8.5'in önerdiği `Pdo\Pgsql::ATTR_DISABLE_PREPARES` kullanılır. Varsayılan prepare ayarı ve açıkça verilen `true` / `false` değerleri korunur.
- PostgreSQL platformundaki `${value}` interpolasyonu `{$value}` biçimine taşınır. Geçersiz boolean değeri için hata mesajı değişmez.

Yama `composer.json` içindeki `extra.patches` alanında tanımlıdır. SHA-256 değeri hem bu tanımda hem `patches.lock.json` içinde kayıtlıdır. `cweagans/composer-patches` 2.0.0 çalışma zamanı bağımlılığıdır; standart `composer install` sırasında, geliştirme paketlerinden bağımsız olarak yamayı uygular. Kurulum ortamında Git bulunmalıdır. `vendor/` altında elle değişiklik yapılması gerekmez.

```sh
composer install --prefer-dist --no-interaction
bash test/run-characterization-tests.sh --colors=never
```

Yama değiştirildiğinde `composer.json` içindeki SHA-256 değeri güncellenmeli, Composer lock dosyası eşitlenmeli ve ardından aşağıdaki komutlar çalıştırılmalıdır:

```sh
composer patches-relock
composer patches-repatch
```

`composer patches-repatch`, yalnızca yama tanımlanmış bağımlılıkları silip lock dosyasından yeniden kurar. Yama veya paket sürümü değiştiğinde temiz kurulum ve testler tekrar doğrulanmalıdır.

DBAL 2'nin [resmî bakım dönemi sona ermiştir](https://www.doctrine-project.org/2022/01/22/sunsetting-dbal-2.html). Bu yama proje tarafından sürdürülen, kapsamı sınırlı bir uyumluluk düzeltmesidir; DBAL 2 için genel bir PHP 8.5 destek taahhüdü değildir. ORM 2.20.13 korunur; Doctrine'in [ORM 2 bakım politikası](https://www.doctrine-project.org/2025/10/08/an-update-on-the-orm-2-end-of-life.html) PHP sürüm uyumluluğu düzeltmelerini kapsar.
