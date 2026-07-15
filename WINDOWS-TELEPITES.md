# Movie Night – Windowsos indítás Apache módosítása nélkül

Ez a változat külön folyamatként fut a PHP saját webszerverével. A meglévő Apache szerverhez, virtuális hostokhoz és a webes játékhoz nem kell hozzányúlni.

## Működés

- a meglévő Apache marad a jelenlegi portján;
- a Movie Night alapértelmezetten a `8090`-es porton fut;
- az SQLite adatbázis a `data/movies.sqlite` fájlban található;
- nincs Node.js, Nginx, MySQL vagy külön telepítendő szolgáltatás.

## 1. PHP ellenőrzése

PHP 8.1 vagy újabb szükséges, ezekkel a modulokkal:

- `curl`
- `pdo_sqlite`
- `sqlite3`
- `mbstring`

Ha XAMPP van telepítve, a batch fájl automatikusan megpróbálja használni ezt:

```text
C:\xampp\php\php.exe
```

A PHP verzió ellenőrzése:

```bat
C:\xampp\php\php.exe -v
```

A modulok ellenőrzése:

```bat
C:\xampp\php\php.exe -m
```

Ha valamelyik modul hiányzik, nyisd meg a PHP által használt `php.ini` fájlt, és ellenőrizd, hogy ezek a sorok nincsenek pontosvesszővel kikapcsolva:

```ini
extension=curl
extension=mbstring
extension=pdo_sqlite
extension=sqlite3
```

A `php.ini` helyét ezzel lehet megkeresni:

```bat
C:\xampp\php\php.exe --ini
```

## 2. Indítás

Csomagold ki a projektet egy tetszőleges mappába, majd kattints duplán erre:

```text
start-movie-night.bat
```

Az oldal megnyílik itt:

```text
http://localhost:8090/
```

Első indításkor a telepítőoldal jelenik meg. Add meg:

- a TMDb API Read Access Tokent;
- Szabi felhasználónevét és jelszavát;
- Barbi felhasználónevét és jelszavát.

## 3. Elérés másik gépről vagy telefonról

Keresd meg a szerver Windowsos helyi IP-címét:

```bat
ipconfig
```

Például, ha az IPv4-cím `192.168.1.50`, akkor a helyi hálózatról:

```text
http://192.168.1.50:8090/
```

Első indításkor a Windows tűzfal rákérdezhet a PHP hálózati hozzáférésére. Magánhálózaton engedélyezd.

Ha nem jelenik meg a kérdés, rendszergazdai parancssorban létrehozható a szabály:

```bat
netsh advfirewall firewall add rule name="Movie Night 8090" dir=in action=allow protocol=TCP localport=8090 profile=private
```

## 4. Port megváltoztatása

Nyisd meg a `start-movie-night.bat` fájlt Jegyzettömbbel, és módosítsd ezt:

```bat
set "PORT=8090"
```

Például:

```bat
set "PORT=8091"
```

## 5. Leállítás

Abban a parancssori ablakban, amelyben a Movie Night fut, nyomj:

```text
Ctrl+C
```

A meglévő Apache ettől nem áll le.

## 6. Automatikus indítás Windowszal

Nyomd meg a `Win+R` billentyűket, majd írd be:

```text
shell:startup
```

Ide készíts parancsikont a `start-movie-night.bat` fájlhoz. Ebben az esetben bejelentkezéskor elindul a Movie Night is.

## 7. Biztonsági mentés

A saját filmek, értékelések és felhasználók ebben az egy fájlban vannak:

```text
data\movies.sqlite
```

Biztonsági mentéshez állítsd le a Movie Nightot, majd másold el ezt a fájlt.

A TMDb-token itt található:

```text
data\config.php
```

Ezt ne tedd nyilvánosan letölthető mappába. A beépített szerver kizárólag a `public` könyvtárat teszi elérhetővé, ezért a `data` mappa nem érhető el a böngészőből.

## Megjegyzés internetes eléréshez

Helyi hálózaton ez a felállás megfelelő. Közvetlen internetes publikáláshoz HTTPS és megfelelő hozzáférés-védelem szükséges; a PHP beépített szerverét elsősorban saját vagy belső használatra érdemes használni.
