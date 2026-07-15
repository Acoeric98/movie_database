# Movie Night – közös filmlista

Kétfelhasználós, PHP + SQLite alapú webalkalmazás. Magyar és angol filmcímre keres a TMDb adatbázisában, gépelés közben ajánl találatokat, és közös listába menti őket.

## Funkciók

- gépelés közbeni filmkeresés magyar és angol címekkel;
- Megnézendő / Megnézve / Kedvenc állapot;
- két külön felhasználó és saját 1–10-es értékelés;
- saját megjegyzés és közös pontszámátlag;
- véletlenszerű „Mit nézzünk ma?” választás;
- mobilbarát felület;
- SQLite-adatbázis, Composer és Node.js nélkül;
- jelszóhashelés, CSRF-védelem, szerveroldalon tárolt TMDb-token.

## Követelmények

- PHP 8.1 vagy újabb;
- PHP modulok: `curl`, `sqlite3`, `pdo_sqlite`, `mbstring`;
- Windows alatt a PHP saját beépített webszervere is használható, az Apache módosítása nélkül;
- TMDb API Read Access Token.

Ubuntu/Debian példa:

```bash
sudo apt update
sudo apt install nginx php-fpm php-curl php-sqlite3 php-mbstring unzip
```


## Windows – külön porton, Apache módosítása nélkül

Windows szerveren a legegyszerűbb indítás:

```text
start-movie-night.bat
```

Ez a PHP saját webszerverét indítja a `8090`-es porton, és nem módosítja a már működő Apache szervert. Részletes leírás: `WINDOWS-TELEPITES.md`.

## Telepítés Nginx alatt

1. Másold ki a projektet:

```bash
sudo mkdir -p /var/www/movie-night
sudo cp -R movie-night/* /var/www/movie-night/
sudo chown -R www-data:www-data /var/www/movie-night
sudo find /var/www/movie-night -type d -exec chmod 750 {} \;
sudo find /var/www/movie-night -type f -exec chmod 640 {} \;
sudo chmod 750 /var/www/movie-night/public
```

2. A `deploy/nginx.conf.example` fájlt másold az Nginx konfigurációjába, és módosítsd:

- `server_name`;
- a PHP-FPM socket verzióját, például `php8.2-fpm.sock` vagy `php8.3-fpm.sock`.

```bash
sudo cp deploy/nginx.conf.example /etc/nginx/sites-available/movie-night
sudo ln -s /etc/nginx/sites-available/movie-night /etc/nginx/sites-enabled/movie-night
sudo nginx -t
sudo systemctl reload nginx
```

3. Nyisd meg böngészőben:

```text
http://A-SZERVER-CIME/install.php
```

4. Add meg a TMDb Read Access Tokent, valamint a két felhasználó nevét és jelszavát.

5. Telepítés után jelentkezz be. Az `install.php` többé nem fut le, amíg létezik a konfiguráció és az adatbázis.

## Fontos biztonsági rész

A webszerver dokumentumgyökere **a `public` könyvtár legyen**, ne a projekt gyökere. Így a `data/config.php` és a `data/movies.sqlite` nem érhető el böngészőből.

Éles internetes használatnál állíts be HTTPS-t, például Certbottal.

## Biztonsági mentés

A teljes saját adatbázis itt van:

```text
data/movies.sqlite
```

Mentés előtt célszerű röviden leállítani a PHP-FPM-et vagy az oldalt karbantartási módba tenni. A legegyszerűbb mentés:

```bash
cp data/movies.sqlite data/movies-$(date +%F).sqlite.bak
```

## TMDb-token

A TMDb-fiókban az API beállításoknál található **API Read Access Token** kell. Nem az API kulcsot, hanem a hosszú Bearer tokent add meg.

A filmadatokat és képeket a TMDb biztosítja. Az alkalmazás nem a TMDb támogatásával vagy jóváhagyásával készült.

## Gyakoribb hibák

### „A data könyvtár nem írható”

```bash
sudo chown -R www-data:www-data /var/www/movie-night/data
sudo chmod 770 /var/www/movie-night/data
```

### Üres keresési találatok vagy TMDb hiba

- ellenőrizd a tokent;
- ellenőrizd, hogy a szerver eléri-e a `api.themoviedb.org` címet;
- ellenőrizd, hogy a PHP `curl` modul aktív-e:

```bash
php -m | grep -E 'curl|sqlite|mbstring'
```

### 502 Bad Gateway

Az Nginx konfigurációban valószínűleg nem a telepített PHP-FPM socket szerepel:

```bash
ls /run/php/
```

## Könyvtárszerkezet

```text
movie-night/
├── data/                 # konfiguráció és SQLite adatbázis
├── deploy/               # Nginx és Apache példák
├── public/               # kizárólag ez legyen weben elérhető
│   ├── api/
│   ├── assets/
│   ├── index.php
│   ├── install.php
│   ├── login.php
│   └── logout.php
├── src/
│   ├── bootstrap.php
│   └── schema.sql
└── README.md
```
