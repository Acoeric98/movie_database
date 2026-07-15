# Frissítés regisztrációs és páros módra

1. Állítsd le a Movie Night BAT ablakát `Ctrl+C`-vel.
2. Mentsd el a jelenlegi `data` mappát, főleg a `movies.sqlite` és `config.php` fájlokat.
3. Másold rá az új verzió fájljait a régi projektmappára.
4. A saját régi `data` mappádat hagyd meg.
5. Indítsd el újra a `start-movie-night.bat` fájlt.

Az első megnyitás automatikusan migrálja a régi adatbázist. A meglévő felhasználók és filmek egy közös páros listába kerülnek, semmit nem kell újratelepíteni.

Új oldalak:
- `/register.php` – regisztráció
- `/account.php` – páros mód és meghívókód

Regisztrációkor választható:
- Egyedül: külön saját lista
- Párban: új közös lista, meghívókóddal
- Csatlakozás: meglévő pár meghívókódjával
