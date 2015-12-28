# ingatlan.com API

Az [ingatlan.com](http://ingatlan.com) API felülete hirdetések betöltését teszi lehetővé ingatlanirodák számára.
Az API szabványos [REST](https://hu.wikipedia.org/wiki/REST) konvenciókat követ, az adatok [JSON](http://json.org/) formátumban közlekednek.
A JSON válaszok a [JSend](https://labs.omniti.com/labs/jsend) ajánlás szerinti formátumot követik.

Az azonosításra [JSON Web Token](http://jwt.io/) technológiát alkalmaz.

Az API végpontjai megtekinthetőek ezek a címen: [https://api.ingatlan.com/v1/doc](https://api.ingatlan.com/v1/doc)
 
# ingatlan.com API kliens

A kliens egy olyan PHP csomag, amely az API hívások bemutatásán kívül több hasznos funkció implementációját tartalmazza:
  
- hirdetések szinkronizálása
- optimális fotószinkronizálás, átméretezéssel

## példakód

Egy példa az example/example.php fájlban tekinthető meg.

