![ingatlan.com](http://ingatlan.com/images/logo.png) 
# Automata Betöltés (API) [![Build Status](https://travis-ci.org/ingatlancom/api-client.svg?branch=master)](https://travis-ci.org/ingatlancom/api-client)

A rendszer célja az, hogy az [ingatlan.com](http://ingatlan.com/) előfizetéssel rendelkező ingatlanközvetítők a saját nyilvántartó rendszerükből interfészen keresztül tölthessék fel és kezelhessék a hirdetéseiket. Az aktiválási igényt az ügyfélszolgálati kapcsolattartóknál kell jelezni.

**FONTOS:**

* Automata Betöltés (API) használatával beküldött hibás adatok (amelyek a gépi validáción nem akadnak fent) megjelenéséért a Szolgáltató nem vállal felelősséget. Azok az ügyfelek, akik Automata Betöltéssel töltenek fel hirdetéseket tudomásul veszik, hogy a referensek által az ingatlan.com admin felületen felvitt módosításaik a következő betöltéssel felülírásra kerülnek abban az esetben ha azt a saját rendszerükben nem módosították.

* Amennyiben az ingatlan.com-on szeretne hirdetést feltölteni / törölni / módosítani, ezt nem az ingatlan.com admin rendszerben kell megtenni, hanem a saját rendszerben kell frissíteni és jelezni a helyi informatikusnak / rendszergazdának, hogy indítsa el a betöltést.

* Az Automata Betöltés nem kezeli a liciteket, kiemeléseket, referenseket. Ezt továbbra is az adminisztrációs felületen végezheti el a referens.

* Automata Betöltéssel nem lehet "új építésű" hirdetéseket feladni. Amennyiben a “conditionType=3”-al érkezik hirdetés azt “conditionType=4” állapotba mentjük, és jelezzük, hogy lépjen be a felületre, és ott manuálisan állítsa be, hogy ezek közül melyikeket szeretné új építésű állapotba tenni (csak annyit fog tudni átállítani, amennyi új ép. kiegészítőt vett). Ha egyszer beállította egy hirdetésnél az állapotot (és még van aktív kerete), akkor annál a hirdetésnél újrabetöltéskor nem írjuk felül az állapotot.

## Technikai információk

API URL: [https://api.ingatlan.com](https://api.ingatlan.com/)

Az API szabványos [REST](https://hu.wikipedia.org/wiki/REST) konvenciókat követ, az adatok [JSON](http://json.org/) formátumban kerülnek átadásra. A JSON válaszok a [JSend](https://labs.omniti.com/labs/jsend) ajánlás szerinti formátumot követik.

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

Mivel az azonosító szerepelni fog az URL-ben is, javasoljuk, hogy ne tartalmazzon egyéb, speciális karaktereket.

A hirdetés paramétereinek listája és magyarázata itt tekinthető meg: [https://api.ingatlan.com/v1/doc/fields](https://api.ingatlan.com/v1/doc/fields)

A hirdetés paramétereinél értelemszerűen a kötelező mezők kötelezően kitöltendőek a felsorolt értékkészletből. Javasoljuk, hogy az opcionális mezők is kitöltve érkezzenek.** A hibás vagy hiányos paraméterekkel érkező hirdetések nem kerülnek feltöltésre. 
**
Ha valamely paraméter hiányzik vagy hibás, az API visszajelzi a hibát a [JSend](https://labs.omniti.com/labs/jsend) ajánlás szerinti formátumban. A lehetséges hibaüzenetek listája is a fenti dokumentációban látható.

**A megjegyzés (description) mező** lehet teljesen üres, vagy tartalmazhat 3 karakternél hosszabb leírást.

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

A fotó tömb kulcsai:

* ownId: csak válaszban, a kép sajátId-ja

* title: a kép felirata, string(100)

* labelId: képfelirat azonosítója, a [képfeliratok itt találhatóak](https://api.ingatlan.com/doc_references/photo_labels.json)

* md5Hash: csak válaszban, a feltöltött, átméretezett kép md5 hash értéke, segítségével ellenőrizni tudjuk, hogy a kliensnek a későbbi feltöltésekkor szükséges-e újra küldenie a képet

* order: sorrend érték, integer

* imageData: csak kérésben, a kép fájl tartalma, base64-es kódolásban

Amennyiben az ügyfél által megadott kép nem elérhető hibaüzenetet adunk vissza.

## Referensek kezelése

A referensek adatait az office kezelőfelületén kell rögzíteni, az "ingatlanreferensek kezelése" menüpont alatt. Az agentId-vel küldött hirdetéseket akkor tudja a rendszer referenshez rendelni, ha az adott agentId a megfelelő referens adatlapján "saját id"-ként fel van tüntetve. A nem megfelelő id-vel küldött hirdetések az iroda adminisztrátorához kerülnek. Kitöltött agentId hiányában nem lehet referenshez betölteni hirdetést. Ennek kitöltése a referens és az iroda felelőssége. 

Amennyiben Automata Betöltést használ, kérjük, ne alkalmazza a referensek vagy hirdetések mozgatását irodák között. Ilyen esetben lépjen kapcsolatba ingatlan.com-os kapcsolattartójával, és kérjen technikai segítséget.

# ingatlan.com API kliens

[https://github.com/ingatlancom/api-client](https://github.com/ingatlancom/api-client)

A kliens egy olyan PHP [composer](https://getcomposer.org/) csomag, amely az API hívások bemutatásán kívül több hasznos funkció implementációját tartalmazza:

* hirdetések szinkronizálása

* optimális fotószinkronizálás, átméretezéssel

## Step by step guide:

1. [Telepítsük a composert.](https://getcomposer.org/doc/00-intro.md#installation-linux-unix-osx)

2. Hozzunk létre egy composer.json fájlt az alábbi tartalommal:

```json
{
    "require": {
        "ingatlancom/apiclient": "~2.0"
    }
}
```

1. A következő paranccsal indítsuk el a telepítést:

```bash
php composer.phar install
```

1. A letöltött csomagok a vendor mappába kerülnek. Példát a kliens használatára a [vendor/ingatlancom/apiclient/example/example.php](https://github.com/ingatlancom/api-client/blob/master/example/example.php) fájlban találunk.

2. További infók: [https://getcomposer.org/doc/](https://getcomposer.org/doc/)

## Fotófunkciók

### SyncPhotos

#### ForceImageDataUpdate

Alapvető esetben a syncPhotos metódus a képek md5 hash értéke alapján dönti el, hogy változott-e az adott kép, és szükséges-e újra feltölteni az ingatlan.com szervereire. A syncPhotos metódus 3. paraméterében kikapcsolhatjuk ezt az ellenőrzést, hogy a kliens minden esetben töltse fela hirdetés fotóit.

#### Párhuzamos letöltés

A syncPhotos metódus 5. paraméterében azt lehet beállítani, hogy - amennyiben a partner fotói http protokollal kerülnek letöltésre - ezt a kliens egyenként, vagy párhuzamosan végezze. Alapvető esetben a funkció ki van kapcsolva, de ha a partner szervereinek ez nem okoz gondot, nyugodtan bekapcsolható.

A képek átméretezése kliens oldalon történik, ezért [Imagick](http://php.net/manual/en/book.imagick.php) vagy [GD](http://php.net/manual/en/book.image.php) php bővítmény szükséges a  használathoz.

## Példakód

Egy példa az [example/example.php](https://github.com/ingatlancom/api-client/blob/master/example/example.php) fájlban tekinthető meg. A példakód nem kötelezően használandó minta, csak javaslat.

## Migráció 1-es verzióról 2-esre

**FONTOS:** A függőségek frissítésével az api-client szükséges PHP verziója 5.3-ről 5.5-re emelkedett.

Ezenkívül a két alábbi változást le kell követniük a partnereknek:

1. A Stash driver példányosítása a következő módra változott:
```php
$driver = new Stash\Driver\FileSystem(['path' => '/tmp/ingatlancom/']);
```

1. Küldési hibát elkapni a következő módon lehet:
```php
    } catch (\GuzzleHttp\Exception\RequestException $e) {
        print_r($e->getResponse()->getBody()->getContents());
```

