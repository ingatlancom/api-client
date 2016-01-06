# ingatlan.com API

![Travis CI Build Status](https://api.travis-ci.org/ingatlancom/api-client.svg)

A rendszer célja, hogy az [ingatlan.com](http://ingatlan.com) előfizetéssel rendelkező ingatlanközvetítők a saját nyilvántartó rendszerükből interfészen keresztül is feltölthessék és kezelhessék a hirdetéseiket.

Az API igénybevételének feltétele, hogy a közvetítő rendelkezzen aktív előfizetéssel.

 - [Díjszabásunk](http://info.ingatlan.com/office/)
 - [Regisztráció](http://admin.ingatlan.com/service_v2/office.php?page=kapcsolatfelvetel)

Az interfész szolgáltatás díját az ingatlan.com előfizetés tartalmazza. Az aktiválási igényt az ügyfélszolgálati kapcsolattartóknál kell jelezni.

## Technikai információk

API URL: https://api.ingatlan.com

Az API szabványos [REST](https://hu.wikipedia.org/wiki/REST) konvenciókat követ, az adatok [JSON](http://json.org/) formátumban közlekednek.
A JSON válaszok a [JSend](https://labs.omniti.com/labs/jsend) ajánlás szerinti formátumot követik.

Az azonosításra [JSON Web Token](http://jwt.io/) technológiát alkalmaz.

Az API végpontjai megtekinthetőek ezek a címen: [https://api.ingatlan.com/v1/doc](https://api.ingatlan.com/v1/doc)

Az API nem rendelkezik külön CREATE és UPDATE funkciókkal; PUT kérés esetén, ha az adott azonosítóval már létezik erőforrás, akkor frissíti, ha nem, létrehozza azt.

## Adattípusok

Az API két adattípussal dolgozik, ezek a hirdetés és a fotó.

### Hirdetés

Minden hirdetésnek rendelkeznie kell egy (partnerenként egyedi) azonosítóval, csak így tölthető be az ingatlan.com rendszerébe.
Ez egy maximum 15 karakter hosszú string, amely lehetőség szerint megfelel az alábbi reguláris kifejezésnek: 
/^[0-9A-Za-z-_]{1,15}$/
mivel az azonosító szerepelni fog az URL-ben is, javasoljuk, hogy ne tartalmazzon egyéb, speciális karaktereket.

A hirdetés paramétereinek listája és magyarázata itt tekinthető meg: [https://api.ingatlan.com/v1/doc/fields](https://api.ingatlan.com/v1/doc/fields)

Ha valamely paraméter hiányzik vagy hibás, az API visszajelzi a hibát.

Az alábbi mezők nem módosíthatóak:

 - listingType
 - propertyType
 - city

#### Intelligens API

Ha olyan mezőkben is kap adatot, amely az adott ingatlantípusnál nem szerepelhetnek, az esetek többségében kijavítja ezeket, 0/NULL értékekre.

Ha az utca/város/városrész nevet nem tudja értelmezni, keres az ingatlan.com elhelyezkedései között hasonlót, és ha talál, cseréli.

Városok és városrészek listája megtekinthető a [/data](/data/) könyvtárban található fájlokban.

### Fotó

Minden fotónak rendelkeznie kell egy (hirdetésenként egyedi) azonosítóval, csak így tölthető be az ingatlan.com rendszerébe.
Ez egy maximum 32 karakter hosszú string, amely lehetőség szerint megfelel az alábbi reguláris kifejezésnek: 
/^[0-9A-Za-z-_]{1,32}$/

Egy fotó tömb kulcsai:

 - ownId: csak válaszban, a kép sajátId-ja
 - title: a kép felirata, string(100)
 - labelId: képfelirat azonosítója, a [képfeliratok itt találhatóak](data/photo_labels.json)
 - md5Hash: csak válaszban, a feltöltött, átméretezett kép md5 hash értéke, segítségével ellenőrizni tudjuk, hogy a kliensnek szükséges-e feltöltenie a képet
 - order: sorrend érték, integer
 - imageData: csak kérésben, a kép fájl tartalma, base64-es kódolásban
 
## Referensek kezelése
A referensek adatait az office kezelőfelületén kell rögzíteni, az "ingatlanreferensek kezelése" menüpont alatt. Az agentId-vel küldött hirdetéseket akkor tudja a rendszer referenshez rendelni, ha az adott agentId a megfelelő referens adatlapján "saját id"-ként fel van tüntetve. A nem megfelelő id-vel küldött hirdetések az iroda adminisztrátorhoz kerülnek.
 
## Projektek kezelése
A projekteket az office kezelőfelületén lehet kezelni (feladni, módosítani, törölni), és az ott megadott "projekt saját azonosítót" kell az interface átadás során a projekthez tartozó lakások esetén megadni (projectId). Amennyiben a projectid nulla, vagy üres mező, a hirdetés önálló hirdetésként fog megjelenni az adatbázisban, más projectid esetén a rendszer megvizsgálja, hogy az azonosító szerinti projektet rögzítették-e már. A projektben feladható ingatlantípusok a hirdetés leíró mellékletben vannak részletezve. 

 
# ingatlan.com API kliens

A kliens egy olyan PHP [composer](https://getcomposer.org/) csomag, amely az API hívások bemutatásán kívül több hasznos funkció implementációját tartalmazza:
  
- hirdetések szinkronizálása
- optimális fotószinkronizálás, átméretezéssel

## Fotó funkciók

### SyncPhotos

#### ForceImageDataUpdate
Alapesetben, ha egy fotó sajátId alapján már fel van töltve az ingatlan.com rendszerébe, a kliens nem is tölti le a képfájlt.
A syncPhotos metódus 3. paraméterében bekapcsolhatjuk, hogy minden esetben töltse le a partner fotóit, majd az md5Hash érték különbözősége alapján döntse el, hogy szükséges-e feltölteni az API-n keresztül.

#### Párhuzamos letöltés
A syncPhotos metódus 5. paraméterében azt lehet beállítani, hogy - amennyiben a partner fotói http protokollal kerülnek letöltésre - ezt a kliens egyenként, vagy párhuzamosan végezze.
Alapesetben a funkció ki van kapcsolva, de ha a partner szervereinek ez nem okoz gondot, nyugodtan bekapcsolható.

### Sorrendezés
Minden fotó feltöltése után a rendszer automatikusan sorrendezi a képeket. 
Mivel a feltöltés párhuzamosan zajlik, emiatt előfordulhat, hogy a kialakult sorrend nem felel meg a partner rendszerében szereplő sorrenddel.
Emiatt létrehoztuk a 
PUT http://api.ingatlan.docker/v1/ads/{adOwnId}/photoOrder 
parancsot, aminek egyetlen paramétere egy tömb, melyben a feltöltött képek azonosítói szerepelnek, a kívánt sorrendben.


## Példakód

Egy példa az [example/example.php](example/example.php) fájlban tekinthető meg.

