![ingatlan.com](http://ingatlan.com/images/logo.png) 
# Automata Betöltés (API) [![Build Status](https://travis-ci.org/ingatlancom/api-client.svg?branch=master)](https://travis-ci.org/ingatlancom/api-client) [![Latest Stable Version](https://poser.pugx.org/ingatlancom/apiclient/v/stable.svg)](https://packagist.org/packages/ingatlancom/apiclient) [![Total Downloads](https://poser.pugx.org/ingatlancom/apiclient/downloads.svg)](https://packagist.org/packages/ingatlancom/apiclient) [![License](https://poser.pugx.org/ingatlancom/apiclient/license.svg)](https://packagist.org/packages/ingatlancom/apiclient)

A rendszer célja az, hogy az [ingatlan.com](http://ingatlan.com/) előfizetéssel rendelkező ingatlanközvetítők a saját nyilvántartó rendszerükből interfészen keresztül tölthessék fel és kezelhessék a hirdetéseiket. Az aktiválási igényt az ügyfélszolgálati kapcsolattartóknál kell jelezni.

**FONTOS:**

* Automata Betöltés (API) használatával beküldött hibás adatok (amelyek a gépi validáción nem akadnak fent) megjelenéséért a Szolgáltató nem vállal felelősséget. Azok az ügyfelek, akik Automata Betöltéssel töltenek fel hirdetéseket tudomásul veszik, hogy a referensek által az ingatlan.com admin felületen felvitt módosításaik a következő betöltéssel felülírásra kerülnek abban az esetben ha azt a saját rendszerükben nem módosították.

* Amennyiben az ingatlan.com-on szeretne hirdetést feltölteni / törölni / módosítani, ezt nem az ingatlan.com admin rendszerben kell megtenni, hanem a saját rendszerben kell frissíteni és jelezni a helyi informatikusnak / rendszergazdának, hogy indítsa el a betöltést.

* Az Automata Betöltés nem kezeli a liciteket, kiemeléseket, referenseket. Ezt továbbra is az adminisztrációs felületen végezheti el a referens.

* Automata Betöltéssel nem lehet "új építésű" hirdetéseket feladni. Amennyiben a “conditionType=3”-al érkezik hirdetés azt “conditionType=4” állapotba mentjük, és jelezzük, hogy lépjen be a felületre, és ott manuálisan állítsa be, hogy ezek közül melyikeket szeretné új építésű állapotba tenni (csak annyit fog tudni átállítani, amennyi új ép. kiegészítőt vett). Ha egyszer beállította egy hirdetésnél az állapotot (és még van aktív kerete), akkor annál a hirdetésnél újrabetöltéskor nem írjuk felül az állapotot.

## Technikai információk

API URL: [https://api.ingatlan.com](https://api.ingatlan.com/)

Az API szabványos [REST](https://hu.wikipedia.org/wiki/REST) konvenciókat követ és az adatokat [JSON](http://json.org/) formátumban kell beküldeni. A JSON válaszok a [JSend](https://labs.omniti.com/labs/jsend) ajánlás szerinti formátumot követik.

Az azonosításra [JSON Web Token](http://jwt.io/) technológiát alkalmaz. Az API login token érvényessége 1 óra.

Az API végpontjai megtekinthetőek ezek a címen: [https://api.ingatlan.com/v1/doc](https://api.ingatlan.com/v1/doc)

Az API nem rendelkezik külön CREATE és UPDATE funkciókkal; PUT kérés esetén, ha az adott azonosítóval már létezik erőforrás, akkor frissíti; ha nem, létrehozza azt.

Kérjük, iratkozzon fel a [github repository](https://github.com/login?return_to=%2Fingatlancom%2Fapi-client) frissítéseire, és új verzió kikerülésekor frissítse a klienst! 

## Fejlesztés

Az API ügyfél oldali üzembe állítása során technikai segítséget nyújtunk.

### Ez tartalmazza:

* teszthozzáférés biztosítása

* éles üzembe állításkor előkészületek

* az ingatlan.com rendszerében már jelen lévő hirdetések megfeleltetése az API-n keresztül beküldöttekkel ( kizárólag tökéletesen megegyező adatok esetekben párosíthatók).

### Nem tartalmazza:

* egyedi igények ingatlan.com oldali fejlesztése

* beküldendő paraméterek módosítása

* tesztirodák létrehozása az éles rendszerekben

* ügyfél oldali hibák debugolása/javítása

* éles rendszerbe hibásan küldött hirdetések/adatok visszaállítása

Korszerű PHP kódoláshoz kiegészítő információk:

* [http://www.php-fig.org/psr/](http://www.php-fig.org/psr/)

* [http://hu.phptherightway.com/](http://hu.phptherightway.com/)

## Teszt környezet

Az éles környezettől független tesztrendszer, amelyhez a hozzáférést szintén az ügyfélszolgálati kapcsolattartótól kell kérni. Az authentikáció kizárólag "https" protokollon keresztül elérhető. Ide csak bizonyos adatokat szinkronizálunk az éles rendszerből, pl. a referensek és a projektek adatait a tesztkörnyezet nem tartalmazza. A teszt környezet egy sandbox, nem áll módunkban az éles infrastruktúra egészét klónozni és karbantartani. Az ide feltöltött hirdetések a rendszeres adatbázis-karbantartások során törlésre kerülhetnek. Amennyiben egy hirdetés betöltése sikeres, azt az API válaszban visszaadjuk. A teszt és az éles környezet egyszerre nem használható. 

Teszt URL: [https://apitest.ingatlan.com](https://apitest.ingatlan.com/)

## A fejlesztés menete

1. Teszthozzáférés biztosítása

2. [https://github.com/ingatlancom/api-client](https://github.com/ingatlancom/api-client) repository feliratkozás 
(ez azért fontos, mert minden egyes frissítésről, javításról automatikusan értesülnek a felhasználók.) 

3. Ügyfél oldali fejlesztés

4. Ügyfél oldali tesztelés az apitest.ingatlan.com sandboxban

5. Amennyiben késznek ítéljük a fejlesztést (nem az ingatlan.com ítéli meg), éles hozzáférés kérése

6. Az apitest.ingatlan.com url cseréje a kódban api.ingatlan.com-ra.

## Adattípusok

Az API két adattípussal dolgozik, ezek a hirdetés és a fotó.

### Hirdetés

Minden hirdetésnek rendelkeznie kell egy (partnerenként egyedi) azonosítóval (ownId), csak így tölthető be az ingatlan.com rendszerébe. Ez egy maximum 15 karakter hosszúságú string, amely lehetőség szerint megfelel az alábbi reguláris kifejezésnek: /^[0-9A-Za-z-_]{1,15}$/  

A hirdetés paramétereinek listája és magyarázata itt tekinthető meg: [https://api.ingatlan.com/v1/doc/fields](https://api.ingatlan.com/v1/doc/fields)

A hirdetés paramétereinél értelemszerűen a kötelező mezők kötelezően kitöltendőek a felsorolt értékkészletből. Javasoljuk, hogy az opcionális mezők is kitöltve érkezzenek.** A hibás vagy hiányos paraméterekkel érkező hirdetések nem kerülnek feltöltésre. 
**
Ha valamely paraméter hiányzik vagy hibás, az API visszajelzi a hibát a [JSend](https://labs.omniti.com/labs/jsend) ajánlás szerinti formátumban. A lehetséges hibaüzenetek listája is a fenti dokumentációban látható.

**A megjegyzés (description) mező** lehet teljesen üres, vagy tartalmazhat 3 karakternél hosszabb leírást.

**A fűtés (heatingType) mező** egy maximum 2 elemű tömb amibe az értékkészlet szerinti fűtéseket lehet megadni. Default értéke 0 és ha 2-nél több elem érkezik benne, akkor az első kettőt menti el. 

**Az alábbi mezők nem módosíthatók:**

* listingType

* propertyType

* city

* projectId

Amennyiben ezekbe nem megfelelő adat került, a hirdetés törlés után új sajatId-vel adható fel újra.

**Kötelező mezők:**

* listingType

* agenciesAccepted

* price_type

* price

* propertyType

* propertySubtype

* AreaSize

* LotSize

* city

* ownId

* roomCount

#### Intelligens API

Ha olyan mezőkben is kap adatot, amelyek az adott ingatlantípusnál nem szerepelhetnek, az esetek többségében kijavítja ezeket, 0/NULL értékekre. Ezek megjelenítésére kitérünk a [mintakódban](https://github.com/ingatlancom/api-client/blob/master/example/example.php).

A hirdetések egyik legfontosabb jellemzője az elhelyezkedés, ezért a feltöltött adatokat az API minden esetben leellenőrzi. A pontatlanul megadott címeket a rendszer megpróbálja valós elhelyezkedési adatokra javítani, de az esetleges hibás megjelenésért a Szolgáltató nem vállal felelősséget.

A városok és városrészek listája megtekinthető [az alábbi tömörített állományban](https://api.ingatlan.com/doc_references/doc_references.zip).

Az Automata Betöltés használatakor, a fentiekben jelzett tömörített állományokban található elhelyezkedési adatokat fogadjuk el. Amennyiben pl. "nem megfelelő városrész" hibát tapasztalunk, a fenti állományban lévőre kell azt az ügyfél oldalán javítani. Amennyiben az ingatlan.com térképadatbázisában hibát talál, kérjük jelezze felénk. 

### Megfeleltető funkció

Az Automata Betöltés beüzemelése előtt az ingatlan.com felületén létrehozott hirdetések egy, a betöltésbe épített logika alapján összepárosodnak a betöltés során abban az esetben, ha tökéletesen egyező paraméterekkel töltődnek be. Azok a hirdetések, amelyek nem megfeleltethetők, törlésre kerülhetnek.

### Fotó

Minden fotónak rendelkeznie kell egy (hirdetésenként egyedi) azonosítóval, csak így tölthető be az ingatlan.com rendszerébe. Ez egy maximum 32 karakter hosszú string, amely lehetőség szerint megfelel az alábbi reguláris kifejezésnek: /^[0-9A-Za-z-_]{1,32}$/

A fotó tömb kulcsai kép feltöltéskor:

* ownId: csak válaszban, a kép sajátId-ja

* title: a kép felirata, string(100)

* labelId: a képfelirat azonosítója, opcionális. A [lehetséges képfeliratok ebben az állományban találhatók](https://github.com/ingatlancom/api-client/blob/master/lib/ApiClient/Enum/PhotoLabelEnum.php).

* order: sorrend érték, integer

* imageData: csak kérésben, a kép fájl tartalma, base64-es kódolásban

Amennyiben az ügyfél által megadott kép nem elérhető hibaüzenetet adunk vissza. Az ingatlan.com rendszerébe 20 képet lehet feltölteni, ez vonatkozik az Automata Betöltésre is. 

Képek lekérdezésekor a következő kulcsok szerepelnek még a tömbben:

* md5Hash: a feltöltött, átméretezett kép MD5 hash értéke, segítségével ellenőrizni tudjuk, hogy a kliensnek a későbbi feltöltésekkor szükséges-e újra küldenie a képet

* hasForbiddenWatermarkOrLogo: a kép megfelel-e az ÁSZF-ben leírtaknak (nem tartalmazhat logót, vízjelet illetve feliratot (az alaprajzokon szereplő jelölések kivételével)). Amennyiben itt true értéket talál, az azt jelenti, hogy 2017. november 15. után az adott képet nem jelenítjük meg a keresők számára. Ilyen esetben kérjük, töltse fel a kép eredeti, nem manipulált változatát. Amennyiben az eredeti képnél is true érték szerepel, kérjük lépjen be az [adminisztrációs felületünkön](https://admin.ingatlan.com/belepes) és a hirdetés szerkesztése oldalon a szabálytalannak jelölt fotón szereplő zászló ikonnal jelentse be a hibás működést. Ezután moderátoraink fogják ellenőrizni és elbírálni a képet.

## Referensek kezelése

A referensek adatait az office kezelőfelületén kell rögzíteni, az "ingatlanreferensek kezelése" menüpont alatt. Az agentId-vel küldött hirdetéseket akkor tudja a rendszer referenshez rendelni, ha az adott agentId a megfelelő referens adatlapján "saját id"-ként fel van tüntetve. A nem megfelelő id-vel küldött hirdetések az iroda adminisztrátorához kerülnek. Kitöltött agentId hiányában nem lehet referenshez betölteni hirdetést. Ennek kitöltése a referens és az iroda felelőssége. 

Amennyiben Automata Betöltést használ, kérjük, ne alkalmazza a referensek vagy hirdetések mozgatását irodák között. Ilyen esetben lépjen kapcsolatba ingatlan.com-os kapcsolattartójával, és kérjen technikai segítséget.

# ingatlan.com API kliens

[https://github.com/ingatlancom/api-client](https://github.com/ingatlancom/api-client)

A kliens egy olyan PHP [composer](https://getcomposer.org/) csomag, amely az API hívások bemutatásán kívül több hasznos funkció implementációját tartalmazza:

* hirdetések szinkronizálása

* optimális fotószinkronizálás, átméretezéssel

## Telepítés

1. [Telepítsük a composert.](https://getcomposer.org/doc/00-intro.md#installation-linux-unix-osx) (További infók: [https://getcomposer.org/doc/](https://getcomposer.org/doc/))

2. Hozzunk létre egy composer.json fájlt az alábbi tartalommal:

```json
{
    "require": {
        "ingatlancom/apiclient": "~3.0"
    }
}
```

3. A következő paranccsal indítsuk el a telepítést:

```bash
php composer.phar install
```

4. A letöltött csomagok a vendor mappába kerülnek.

## Használat

A kliens használatához felhasználónév és jelszó szükséges, amelyet az ingatlan.com kapcsolattartójától kap meg.

1. Az API kliensbe az autentikáció [JWT](https://jwt.io) tokenekkel történik. Sikeres azonosítás után a kliens egy tokent kap az API-tól. Ezt a tokent tároljuk le egy [Stash Pool](http://www.stashphp.com)-ba, hogy ne kelljen minden hívás előtt belépnünk. Pool példányosítása:

```php
$driver = new Stash\Driver\FileSystem(['path' => '/tmp/ingatlancom/']);
$pool = new Stash\Pool($driver);
```
(A "/tmp/ingatlancom" helyett adja meg azt a könyvtárat, ahol a program ideiglenes fájlokat tárolhat a szerveren.)

2. Példányosítsuk az API klienst:

```php
$apiUrl = 'https://apitest.ingatlan.com';
$apiClient = new \IngatlanCom\ApiClient\ApiClient($apiUrl, $pool);
```

(Éles rendszerre történő betöltés esetén az $apiUrl értéke "https://api.ingatlan.com".)

3. Bejelentkezés:

```php
$apiClient->login('username', 'password');
```

Az alább következő műveletek csak a bejelentkezés meghívása után végezhetők el.

### Hirdetés feltöltése

Az $ad tömbben adja meg a hirdetés paramétereit. (A beküldhető mezők pontos leírását az [alábbi linken](https://api.ingatlan.com/v1/doc/fields) tekintheti meg.)

```php
$ad = [
    'ownId'           => 'x149395',
    'listingType'     => 1,
    'propertyType'    => 1,
    'propertySubtype' => 2,
    'priceHuf'        => 17500000
     ...
];
$apiClient->putAd($ad);
```

### Hirdetés lekérdezése

A x149395 saját id-jú hirdetés lekérdezése:
```php
$ad = $apiClient->getAd('x149395');
```
Sikeres hívás esetén az $ad változó egy tömb lesz a hirdetés értékeivel.

### Hirdetés törlése

A x149395 saját id-jú hirdetés törlése:
```php
$apiClient->deleteAd('x149395');
```
(Fizikailag a hirdetés nem törlődik, csak a státusza fog "törlöm, de megtartom" státuszra váltani.)

### Hirdetés elrejtése

Az `isHiddenOnIcom` nevű paraméterrel állítható, hogy a hirdetés megjelenjen-e az ingatlan.com-on. Fontos, hogy ez az opció nem törli a hirdetést, és a státuszát sem módosítja, egyszerűen csak nem lesz látható a keresők számára.

Az x149395 saját id-jú hirdetés elrejtéséhez állítsuk a mező értékét `1`-re, ahogy [az a paraméterek listájában is látható](https://api.ingatlan.com/v1/doc/fields):


```php
$ad = [
    'ownId'           => 'x149395',
    'listingType'     => 1,
    'propertyType'    => 1,
    'propertySubtype' => 2,
    'priceHuf'        => 17500000,
    'isHiddenOnIcom'  => 1
     ...
];
$apiClient->putAd($ad);
```

(A hirdetés megjelenítéséhez pedig értelemszerűen `0`-s értékkel kell ezt a paramétert beküldeni.)

### Iroda összes hirdetés azonosítójának lekérdezése

```php
$ids = $apiClient->getAdIds();
```
Sikeres hívás esetén az $ids egy tömb lesz a feltöltött hirdetések id-ival.

### Hirdetések szinkronizálása

A syncAds() függvény letörli az ingatlan.com szerveréről az Önök rendszerében már nem szereplő hirdetéseket.

Az $ads tömbben sorolja fel a rendszerükben létező hirdetések saját id-jait:
```php
$ads = [
    'hirdetes1',
    'hirdetes2'
    ...
];
$ids = $apiClient->syncAds($ads);
```

### Képek szinkronizálása

Az x149395 saját id-jú hirdetéshez a fotók szinkronizálása:
```php
use IngatlanCom\ApiClient\Enum\PhotoLabelEnum;

$photos = [
    [
        'ownId'    => 'kep1',
        'order'    => 1,
        'title'    => 'Képfelirat 1',
        'location' => 'http://lorempixel.com/800/600/city/1/',
        'labelId'  => PhotoLabelEnum::KORNYEK
    ],
    [
        'ownId'    => 'kep2',
        'order'    => 2,
        'title'    => 'Képfelirat 2',
        'location' => 'http://lorempixel.com/800/600/city/2/',
    ]
];
$ids = $apiClient->syncPhotos(
    'x149395',
    $photos,
    $forceImageDataUpdate,
    $uploadedPhotos,
    $paralellDownload
);
```

### $photos
A $photos tömbben a feltöltendő fotók [adatai](#fotó) legyenek. A syncPhotos() függvény használatakor a fotó adatai tömbben lehetséges a "location" kulcs használata. Itt meg kell adni a képfájl elérési útját, amely lehet az adott számítógépen elérhető fájl, vagy akár URL is. A kliens a location mező alapján beolvassa a képfájlt, elvégzi rajta az átméretezést (ha szükséges) és feltöltéskor a megfelelő adatként ("imageData") fel fogja küldeni a képfájl tartalmát.

### $forceImageUpdate
Alapvető esetben a syncPhotos() metódus a képek md5 hash értéke alapján dönti el, hogy változott-e az adott kép, és szükséges-e újra feltölteni az ingatlan.com szervereire. A syncPhotos() metódus 3. paraméterében kikapcsolhatjuk ezt az ellenőrzést, hogy a kliens minden esetben töltse fel a hirdetés fotóit.

### $uploadedPhotos
Az $uploadedPhotos paraméter tömbben a szerveren található fotókat kell megadni. Ha ez utóbbit nem tudjuk, célszerű ezt a paramétert null-ra állítani és a kliens automatikusan lekérdezi a képeket a szerverről.

### $paralellDownload
A syncPhotos() metódus 5. paraméterében azt lehet beállítani, hogy - amennyiben a partner fotói http protokollal kerülnek letöltésre - ezt a kliens egyenként, vagy párhuzamosan végezze. Alapvető esetben a funkció ki van kapcsolva, de ha a partner szervereinek ez nem okoz gondot, nyugodtan bekapcsolható.

A képek átméretezése kliens oldalon történik, ezért [Imagick](http://php.net/manual/en/book.imagick.php) vagy [GD](http://php.net/manual/en/book.image.php) php bővítmény szükséges a  használathoz.

### Kép feltöltése

A $photoData tömbben adja meg a fotó adatait. A fotó tömb értékeitről [itt](#fotó) talál információt.

(A putPhoto() használatakor a képfájl adatainál nem használható a "location" kulcs a syncPhotos() függvénnynel ellentétben. Itt kizárólag az imageData kulcs alatt küldhető a képfájl base64 kódolt tartalma.)
```php
$photoData = [
    'ownId'    => 'kep3',
    'order'    => 3,
    'title'    => 'Képfelirat 3',
    'labelId'  => null,
    'imageData => file_get_contents('kepem.jpg')
];
$ids = $apiClient->putPhoto('x149395', $photoData);
```

### Több kép feltöltése

A $photos tömbbe adjon meg több fotót a [Kép feltöltésénél](#kép-feltöltése) látható elemekből.
```php
$ids = $apiClient->putPhotosMulti('x149395', $photos);
```

### Kép törlése

A x149395 saját id-jú hirdetésnél a kep123 saját id-jú kép törlése.
```php
$ids = $apiClient->deletePhoto('x149395', 'kep123');
```

### Több kép törlése

A $photoIds tömbben a törlendő képek saját id-jait kell megadni.

Képek törlése a x149395 saját id-jú hirdetésnél:
```php
$photoIds = ['kep1', 'kep2'];
$ids = $apiClient->deletePhotosMulti('x149395', $photoIds);
```

### Hirdetés képeinek lekérdezése

A x149395 saját id-jú hirdetés képeinek lekérdezése:
```php
$photos = $apiClient->getPhotos('x149395');
```
Sikeres hívás esetén a $photos egy tömb lesz a hirdetés képeinek adataival.

### Hirdetés képeinek sorrendezése

A képek sorrendezése a x149395 saját id-jú hirdetésnél:
```php
$photoOrder = ['kep1', 'kep2', 'kep3'];
$ids = $apiClient->putPhotoOrder('x149395', $photoOrder);
```
A $photoOrder tömbben a képek saját id-i a kívánt sorrendben legyenek.

## API státusz ellenőrzése

A checkApiStatus() függvény hívásával lehetőség van az API állapotát lekérdezni. A függvény true-t ad vissza, ha minden alrendszerünk működik és false-t, ha a betöltés valamilyen hiba miatt nem üzemel. False-t adunk vissza akkor is, ha kliens oldalon van probléma, tehát például nincs internetkapcsolat és a kliens nem éri el a szervereinket.
```php
$isOk = $apiClient->checkApiStatus();
```

## Példakód

Egy példa az [example/example.php](https://github.com/ingatlancom/api-client/blob/master/example/example.php) fájlban tekinthető meg. A példakód nem kötelezően használandó minta, csak javaslat.
