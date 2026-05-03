# Frontend

Vite+ReactJS-el lett elkészítve, Typescript-tel. Form validációra (login form jelenesetben), react-hook-formm + zod lett használva.
Fetcheléshez axios van használva, a backend címét egy .env fájlban lehet megadni.
Feature-based struktúra
UI libraryként a shadcn/ui van használva,

# Symfony

Követtem a doksi által javasolt szerkezetet, a requestek (query, bodyk) dto-kban vannak reprezentálva, és ezek a megfelelő metódussal (``MapQueryString`` vagy ``MapRequestPayload``) vannak mapelve a controllerben.

``XMLParserService`` egy külön service, ami a bemenő XML stringet egy DTO-ba mapeli, és validálja is azt.

Az audit logolás egy eventlistenerrel van megoldva, mert így mindig lefut egy adott entitás módosulásakor, és nem kell minden egyes helyen külön megemlíteni a logolást. Az audit log entitásban tárolom a művelet típusát (CREATE, UPDATE, DELETE), a módosított entitás nevét, az entitás ID-jét, a módosítás időpontját, és egy JSON mezőben a módosítás előtti és utáni értékeket. Ez a ``AuditLogEventListener`` osztályban van implementálva, új changek figyelésére egy-egy sort kell hozzáaadni a ``getTableName`` ``serialize`` és a ``isAuditable`` funkciókhoz.

Az API-ra nem raktam globális authot, a ``security.yml``-ban, kézrefekvőbbnek tűnt, hogy annotációkkal legyen most megoldva.

# AI

A feladat megoldása során Claude Code-ot + inline completet használtam. Claude esetében Opus 4.7, inline complete default github copilotot használtam, a meglévő  entityk generálásához, ``SHOW CREATE TABLE`` SQL lekérdezés alapján, illetve az olyan részekhez, amibe a feladat pontozása nem számít bele. (lsd. ``- Valódi LDAP integráció (a mock interface elég)``).
Leginkább a controller layeren kellet belenyúlni, mert nem nagyon ismerte a mappereket, illetve az entitásokban rosszul írta ki date fieldeket.

Amit kihagytam az auditlogok megjelenítése, az időhiányában, illetve a  

# GO Kód

A Go kódot néztem fejleszéts közben, és ami feltűnt, az a pagination metadata teljes hiánya. Erre sajnos nem volt időm implementálni, de van létrehozva egy ``PaginatedResponseDto`` osztály, hogy ez későbbiekben le legyen kezlve. Ez miatt frontenden a ``Pagination`` komponens egy fix (10) oldalal van megoldva, illetve a limit állítás se lett még oda bekötve.

# Known issuek

- docker compose up után a frontend nem tudja feloldalni a backend címét.

Maximum 1 oldal, amiben többek közt kitérhetsz, az általad fontosnak tartott dolgok mellett:
