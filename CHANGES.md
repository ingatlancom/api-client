# Change Log
Az ingatlancom/api-client csomag minden változását ebben a leírásban kell rögzíteni.

Az ingatlancom/api-client csomag a [Szemantikus verziózás](http://semver.org/) irányelveit követi.

A Changelog a [Keep a Changelog](http://keepachangelog.com) formátumában íródik.

## [3.1.0] - 2017-05-10

## Bekerült
- ApiClient::checkApiStatus(), amivel az automata betöltés rendszerének állapotát lehet lekérdezni. Visszatérési érték: true / false. (True esetén a rendszereink működnek, false esetén valamilyen probléma van szerver vagy kliens oldalon.)

## Javítva
- Képsorrendezés probléma az ApiClient::syncPhotos() hívásakor

## [3.0.0] - 2017-05-05

### Változott
- A PhotoSync osztály össze lett vonva az ApiClient osztállyal. (Ha valaki külön használta a PhotoSync osztályt, akkor mostantól a syncPhotos() metódusát az ApiClient osztályon kell hívnia.)
- Az ApiClient::syncPhotos() hívás mostantól PhotoSyncResult objektumot ad vissza.

## Bekerült
- PhotoSyncResult objektum, amelyet a ApiClient::syncPhotos() metódus ad vissza. Ezen változatlanul meghívhatók az eddig használt getErrors(), getPutPhotoErrors() stb. hibalekérdező metódusok.
- A PhotoSyncResult osztályon új meghívható metódusok, amelyek egy saját id-val indexelt tömbben visszaadják kizárólag a hibaüzeneteket: getFetchPhotoErrorMessages(), getDeletePhotoErrorMessages(), getPutPhotoErrorMessages(), getErrorMessages()

## [2.2.2] - 2017-04-27

### Javítva
- Szerveridők csúszásából fakadó "lejárt vagy hibás token" hibaüzenet javítása
- null Response kezelése PhotoSync osztályban

## [2.2.1] - 2017-04-26

### Javítva
- 800x600-nál nagyobb képek GD-vel történő hibás átméretezésének javítása

## [2.2.0] - 2017-04-13

### Bekerült
- verzió küldése headerben

### Javítva
- képek letöltésének javítása

## [2.1.0] - 2017-04-06

### Bekerült
- a JWT tokent a lejárati idejéig cacheeljük
- token lekérése mindig a cache-ből, illetve ha ott már lejárt, akkor automatikusan új token kérése a szervertől autentikációval

### Javítva
- Stash\Pool cache használatának javítása
