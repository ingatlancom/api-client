![ingatlan.com](http://ingatlan.com/images/logo.png)
# Automata Betöltés (API)

A rendszer célja, hogy az [ingatlan.com](http://ingatlan.com/) előfizetéssel rendelkező ingatlanközvetítők a saját nyilvántartó rendszerükből interfészen keresztül tölthessék fel és kezelhessék a hirdetéseiket. Az aktiválási igényt az ügyfélszolgálati kapcsolattartóknál kell jelezni. 

**FONTOS:**

* Automata Betöltés (API) használatával történő hibás adatok ( melyek a gépi validáción nem akadnak fent ) megjelenéséért a Szolgáltató nem vállal felelősséget. Azok az ügyfelek, akik Automata Betöltéssel töltenek fel hirdetéseket tudomásul veszik, hogy a referensek által az ingatlan.com admin felületen felvitt módosításaik a következő betöltéssel felülírásra kerülnek. abban az esetben ha azt a saját rendszerükben nem módosították. 

* Amennyiben az ingatlan.com-on szeretne hirdetést feltölteni / törölni / módosítani ezt nem az ingatlan.com admin rendszerben kell megtenni, hanem a saját rendszerben kell frissíteni és jelezni a helyi informatikusnak / rendszergazdának, hogy indítsa el a betöltést. 

* Az Automata Betöltés nem kezeli a liciteket, kiemeléseket, referenseket. Ezt továbbra is az adminisztrációs felületen végezheti el a referens.

## Technikai információk

API URL: [https://api.ingatlan.com](https://api.ingatlan.com/)

Az API szabványos [REST](https://hu.wikipedia.org/wiki/REST) konvenciókat követ, az adatok [JSON](http://json.org/) formátumban közlekednek. A JSON válaszok a [JSend](https://labs.omniti.com/labs/jsend) ajánlás szerinti formátumot követik.

Az azonosításra [JSON Web Token](http://jwt.io/) technológiát alkalmaz. Az API login token érvényessége 1 óra.

Az API végpontjai megtekinthetőek ezek a címen: [https://api.ingatlan.com/v1/doc](https://api.ingatlan.com/v1/doc)

Az API nem rendelkezik külön CREATE és UPDATE funkciókkal; PUT kérés esetén, ha az adott azonosítóval már létezik erőforrás, akkor frissíti, ha nem, létrehozza azt.

Kérjük iratkozzon fel a [github repository](https://github.com/login?return_to=%2Fingatlancom%2Fapi-client) frissítéseire és új verzió kikerülésekor  frissítse a klienst.

## Fejlesztés

Az API ügyfél oldali üzembe állítása során technikai segítségnyújtást adunk. 

### Ez tartalmazza:

* teszt hozzáférés biztosítása

* éles üzembe állításkori előkészületek

* az ingatlan.com rendszerében már jelen levő hirdetések megfeleltetése az API-n keresztül beküldöttekel ( kizárólag tökéletesen megegyező adatok esetekben párosíthatóak )

### Nem tartalmazza:

* egyedi igények ingatlan.com oldali fejlesztése

* beküldendő paraméterek módosítása

* teszt irodák létrehozása az éles rendszerekben

* ügyfél oldali hibák debugolása/javítása

Korszerű PHP kódoláshoz kiegészítő információk:

* [http://www.php-fig.org/psr/](http://www.php-fig.org/psr/)

* [http://www.phptherightway.com/](http://www.phptherightway.com/)

## Teszt környezet

Az éles környezettől független teszt rendszer, melyhez a hozzáférést szintén az ügyfélszolgálati kapcsolattartótól kell kérni. Az autentikáció kizárólag "https" protokolon keresztül elérhető. Ide csak bizonyos adatokat szinkronizálunk az éles rendszerből, pl. a referensek és a projektek adatait a teszt környezet nem tartalmazza. A teszt környezet egy sandbox, nem áll módunkban az éles infrastruktúra egészét klónozni és karbantartani. Az ide feltöltött hirdetések a rendszeres adatbázis karbantartások során törlésre kerülhetnek.  Amennyiben egy hirdetés betöltése sikeres, azt az API válaszban visszaadjuk.

Teszt URL: [https://apitest.ingatlan.com](https://apitest.ingatlan.com/)

## Adattípusok

Az API két adattípussal dolgozik, ezek a hirdetés és a fotó.

### Hirdetés

Minden hirdetésnek rendelkeznie kell egy (partnerenként egyedi) azonosítóval (ownId), csak így tölthető be az ingatlan.com rendszerébe. Ez egy maximum 15 karakter hosszú string, amely lehetőség szerint megfelel az alábbi reguláris kifejezésnek: /^[0-9A-Za-z-_]{1,15}$/ mivel az azonosító szerepelni fog az URL-ben is, javasoljuk, hogy ne tartalmazzon egyéb, speciális karaktereket. 

A hirdetés paramétereinek listája és magyarázata itt tekinthető meg: [https://api.ingatlan.com/v1/doc/fields](https://api.ingatlan.com/v1/doc/fields)

A hirdetés paramétereinél értelem szerűen a kötelező mezők kötelezően kitöltendőek a felsorolt értékkészletből. Javasoljuk, hogy az opcionális mezők is kitöltve érkezzenek. A hibás vagy hiányos  paraméterekkel érkező hirdetések nem kerülnek feltöltésre.

Ha valamely paraméter hiányzik vagy hibás, az API visszajelzi a hibát a [JSend](https://labs.omniti.com/labs/jsend) ajánlás szerinti formátumban.

A megjegyzés ( description ) mező vagy teljesen üres lehet, vagy tartalmazhat 3 karakternél hosszabb leírást. 

Az alábbi mezők nem módosíthatóak:

* listingType

* propertyType

* city

#### Intelligens API

Ha olyan mezőkben is kap adatot, amely az adott ingatlantípusnál nem szerepelhetnek, az esetek többségében kijavítja ezeket, 0/NULL értékekre.

A hirdetések egyik legfontosabb jellemzője az elhelyezkedés, ezért a feltöltött adatokat az API minden esetben leellenőrzi. A pontatlanul megadott címeket a rendszer megpróbálja valós elhelyezkedési adatokra javítani, de az esetleges hibás megjelenésért a Szolgáltató nem vállal felelősséget.Városok és városrészek listája megtekinthető a [/data](https://github.com/ingatlancom/api-client/blob/master/data) könyvtárban található fájlokban.

### Megfeleltető funkció

Az Automata Betöltés beüzemelése előtt az ingatlan.com felületén létrehozott hirdetések egy a betöltésbe épített logika alapján összepárosodnak a betöltés során abban az esetben, ha tökéletesen egyező paraméterekkel töltődnek be. Azok a hirdetések, amelyek nem megfeleltethetőek  törlésre kerülhetnek. 

### Fotó

Minden fotónak rendelkeznie kell egy (hirdetésenként egyedi) azonosítóval, csak így tölthető be az ingatlan.com rendszerébe. Ez egy maximum 32 karakter hosszú string, amely lehetőség szerint megfelel az alábbi reguláris kifejezésnek: /^[0-9A-Za-z-_]{1,32}$/

A fotó tömb kulcsai:

* ownId: csak válaszban, a kép sajátId-ja

* title: a kép felirata, string(100)

* labelId: képfelirat azonosítója, a [képfeliratok itt találhatóak](https://github.com/ingatlancom/api-client/blob/master/data/photo_labels.json)

* md5Hash: csak válaszban, a feltöltött, átméretezett kép md5 hash értéke, segítségével ellenőrizni tudjuk, hogy a kliensnek a későbbi feltöltésekkor szükséges-e újra küldenie a képet

* order: sorrend érték, integer

* imageData: csak kérésben, a kép fájl tartalma, base64-es kódolásban

## Referensek kezelése

A referensek adatait az office kezelőfelületén kell rögzíteni, az "ingatlanreferensek kezelése" menüpont alatt. Az agentId-vel küldött hirdetéseket akkor tudja a rendszer referenshez rendelni, ha az adott agentId a megfelelő referens adatlapján "saját id"-ként fel van tüntetve. A nem megfelelő id-vel küldött hirdetések az iroda adminisztrátorhoz kerülnek. Kitöltött agentId hiányában nem lehet referenshez betölteni hirdetést. Ennek kitöltése a referens és az iroda felelőssége.

## Projektek kezelése

A projekteket az office kezelőfelületén lehet kezelni (feladni, módosítani, törölni), és az ott megadott "projekt saját azonosítót" kell az interface átadás során a projekthez tartozó lakások esetén megadni (projectId). Amennyiben a projectid nulla, vagy üres mező, a hirdetés önálló hirdetésként fog megjelenni az adatbázisban, más projectid esetén a rendszer megvizsgálja, hogy az azonosító szerinti projektet rögzítették-e már. A projektben feladható ingatlantípusok a hirdetés leíró mellékletben vannak részletezve.

# ingatlan.com API kliens

https://github.com/ingatlancom/api-client

A kliens egy olyan PHP [composer](https://getcomposer.org/) csomag, amely az API hívások bemutatásán kívül több hasznos funkció implementációját tartalmazza:

* hirdetések szinkronizálása

* optimális fotószinkronizálás, átméretezéssel

## Step by step guide:

1. [Telepítsük a composert](https://getcomposer.org/doc/00-intro.md#installation-linux-unix-osx)

2. Hozzunk létre egy composer.json fájlt az alábbi tartalommal:

```
{
    "require": {
        "ingatlancom/apiclient": "~1.0.0"
    }
}
```

3. A következő paranccsal indítsuk el a telepítést:

```
php composer.phar install
```


4. A letöltött csomagok a vendor mappába kerülnek. Példát a kliens használatára a [vendor/ingatlancom/apiclient/example/example.php](https://github.com/ingatlancom/api-client/blob/master/example/example.php) fájlban találunk.

5. További infók: [https://getcomposer.org/doc/](https://getcomposer.org/doc/)

## Fotó funkciók

### SyncPhotos

#### ForceImageDataUpdate

Alapesetben, a syncPhotos metódus a képek md5 hash értéke alapján dönti el, hogy változott-e az adott kép, és szükséges-e újra feltölteni az ingatlan.com szervereire. A syncPhotos metódus 3. paraméterében kikapcsolhatjuk ezt az ellenőrzést, hogy a kliens minden esetben töltse fela hirdetés fotóit.

#### Párhuzamos letöltés

A syncPhotos metódus 5. paraméterében azt lehet beállítani, hogy - amennyiben a partner fotói http protokollal kerülnek letöltésre - ezt a kliens egyenként, vagy párhuzamosan végezze. Alapesetben a funkció ki van kapcsolva, de ha a partner szervereinek ez nem okoz gondot, nyugodtan bekapcsolható.

## Példakód

Egy példa az [example/example.php](https://github.com/ingatlancom/api-client/blob/master/example/example.php) fájlban tekinthető meg.

