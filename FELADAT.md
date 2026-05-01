# Fullstack fejlesztő — gyakorlati feladat

## A helyzet

Backend stackünk konszolidációjának részeként a Go-ban írt
mikroszervizeinket fokozatosan Symfony-ra migráljuk. Egy 
`auction-service` szervizt kapsz meg (`legacy-go/`) és
a feladat ezt portolni Symfony-ra, ráadni új funkciókat (módosító
endpointok + audit log), és egy minimális frontendet írni hozzá.

## Időkeret

**5-8 óra aktív munkaidő.** Ennyi idő alatt nem fogsz minden részletet
profi minőségben átadni, és nem is várjuk, hogy ettől többet szánj rá. 
Inkább azt nézzük, hogy mire prioritizálsz, mit dokumentálsz, és mennyire pontosan reprodukálod
a meglévő viselkedést. Kérlek, jelezd hogy mennyi aktív időt alatt végeztél, illetve minden mást is
a egy readme.md-ben, amit fontosnak gondolsz.

## Indítás

```bash
docker compose up --build
```

Ez felhúzza:

| Service   | Port | Szerep                                                             |
|---------  |------|--------------------------------------------------------------------|
| postgres  | 5433 | Két DB: `auctions_legacy` (Go-é) és `auctions` (Symfony-é, üresen) |
| redis     | 6380 | Token store + login event log                                      |
| legacy-go | 8080 | A portolandó referencia szerviz, működik, 10 minta árveréssel      |

A `loader` service induláskor egyszer lefut és betölti a 10 minta
XML-t a Go szervizbe. Ezután a Go API-n keresztül elérhetők, és
parity-tesztelésre használhatod őket.

A te Symfony és frontend service-eidet a `docker-compose.yml`-be vedd
fel — kommentelt példa van benne. Javasolt portok: `8090` (Symfony),
`3000` (frontend). A migrált eljárásba neked kell betölteni az xml-ek, hasonlóan egy loaderrel, vannak
hibás xml-ek, kezelje azokat is az elvárt módon és próbálja meg betölteni.

### Tesztelés indítás után - GO szerviz:

### Health
```bash / cmd
curl http://localhost:8080/health

#### Linux Bejelentkezés és token / Lista a Go szervizből
TOKEN=$(curl -s -X POST -H 'Content-Type: application/json' -d '{"username":"kovacs.janos","password":"Kovacs123!"}' http://localhost:8080/auth/login | sed -n 's/.*"token":"\([^"]*\)".*/\1/p')
echo $TOKEN
curl -H "Authorization: Bearer $TOKEN" http://localhost:8080/auctions
```

#### Windows (PowerShell):
# Bejelentkezés és token
```powershell
$body = '{"username":"kovacs.janos","password":"Kovacs123!"}'
$resp = Invoke-RestMethod -Uri http://localhost:8080/auth/login -Method POST -ContentType "application/json" -Body $body
$token = $resp.token
$token
Invoke-RestMethod -Uri http://localhost:8080/auctions -Headers @{ "Authorization" = "Bearer $token" }
```

#### DB ellenőrzés:

```bash
# Linux/Mac/PowerShell/Win:
GO: docker exec -it mbvk_teszt-postgres-1 psql -U app -d auctions_legacy -c "SELECT count(*) FROM auctions;"
Symfony: docker exec -it mbvk_teszt-postgres-1 psql -U app -d auctions -c "SELECT count(*) FROM auctions;"

A `auctions_legacy` DB-ben 10 árverést kell látnod. Az `auctions` DB-ben (Symfony-é) a táblák léteznek, de **üresek**.

#### Redis ellenőrzés:
```bash
# Linux/Mac/PowerShell/Win:
docker exec mbvk_teszt-redis-1 redis-cli KEYS "auth:*"
docker exec mbvk_teszt-redis-1 redis-cli LRANGE auth:events:recent 0 -1
docker exec mbvk_teszt-redis-1 redis-cli GET "auth:token:<TOKEN_HEX>"
```

### Teszt felhasználók (mock LDAP)

| Felhasználó      | Jelszó           | Szerepkörök                |
|------------------|------------------|----------------------------|
| `kovacs.janos`   | `Kovacs123!`     | ROLE_USER                  |
| `szabo.eva`      | `Szabo456!`      | ROLE_USER                  |
| `admin`          | `AdminPass789!`  | ROLE_USER, ROLE_ADMIN      |

## Mit kell megcsinálnod

### Backend (Symfony 6.4 LTS vagy 7.x)

A te Symfony-d az **`auctions` DB-t** használja, ami a séma megléte
mellett üresen indul (nincs benne adat). A Doctrine entity-ket
illeszd a meglévő sémához. A saját Doctrine migrációid az általad
hozzáadott módosítások (új mezők, új tábla az audit log-hoz, stb.).

A `legacy-go/main.go`-ban szereplő endpointokat portold át
ekvivalens viselkedéssel:

| Method | Path                          | Auth       | Leírás                                                      |
|--------|-------------------------------|------------|-------------------------------------------------------------|
| POST   | `/api/auth/login`             | publikus   | Mock LDAP, Bearer token                                     |
| GET    | `/api/auth/me`                | Bearer     | Aktuális felhasználó                                        |
| GET    | `/api/auth/recent-logins`     | ROLE_ADMIN | Utolsó N login esemény Redis-ből                            |
| POST   | `/api/auctions/import`        | Bearer     | XML árverés import                                          |
| GET    | `/api/auctions`               | Bearer     | Paginált lista, szűrés `status` és `case_no` szerint        |
| GET    | `/api/auctions/{id}`          | Bearer     | Részletek + asset-ek                                        |

**Új endpointok** a Symfony oldalon (a Go-ban nincsenek):

| Method | Path                          | Auth       | Leírás                                                       |
|--------|-------------------------------|------------|--------------------------------------------------------------|
| PATCH  | `/api/auctions/{id}`          | Bearer     | Státusz-váltás (`pending`→`active`→`closed`/`cancelled`)     |
| DELETE | `/api/auctions/{id}`          | ROLE_ADMIN | Árverés és assets törlése                                    |
| GET    | `/api/auctions/{id}/history`  | Bearer     | Audit log az adott árveréshez                                |

A státusz-átmenetekre rakj értelmes szabályt (pl. `closed` állapotból
nem lehet visszamenni `pending`-be). A választott szabályrendszert
indokold a README-ben. A db műveletek tranzakcióban történjenek.

**Audit log követelmény:**

> Az `auctions` és `assets` táblák minden módosítását
> (INSERT, UPDATE, DELETE) rögzíteni kell egy `audit_log` táblába.
> Soronként: a tábla neve, a rekord ID-je, a művelet, a régi és új
> érték JSON-ben, a módosító felhasználó, és az időpont.
>
> Hogy hogyan oldod meg — alkalmazás-szinten, Doctrine event listenerrel,
> PostgreSQL triggerrel, vagy másként — rád van bízva. A README-ben
> indokold a választást.

### Frontend (React vagy Angular, TypeScript-ben)

Strict mode aktív.

**4 oldal:**

1. **Login** (`/login`) — username + password, hibaüzenet,
   loading state. Token sessionStorage-ban (NEM localStorage-ban — XSS).
2. **Árverés lista** (`/auctions`) — táblázat: case_no, debtor,
   starts_at, asset count, status badge. Szűrés case_no és status
   szerint. Pagináció.
3. **Árverés részletek** (`/auctions/{id}`) — fejléc + műveletek
   (státusz-váltás dropdown, "Auction törlés" admin-only) + asset-lista HUF formázva +
   audit history (collapsible vagy tab szekció).
4. **Login history** (`/admin/logins`) — csak admin számára,
   a `/api/auth/recent-logins` tartalma: timestamp, username,
   success/fail, IP, user agent.

**Globális:**
- Token lejárat 1 óra max (401) → redirect login-ra
- Layout: header user-névvel + logout, navigáció szerepkör szerint
- HUF formázás: `Intl.NumberFormat('hu-HU', { style: 'currency', currency: 'HUF' })`

UI library szabadon választható.

### README a megoldásod mellé

Maximum 1 oldal, amiben többek közt kitérhetsz, az általad fontosnak tartott dolgok mellett:

- Symfony szerkezet és technikai döntések indoklása
- A Go kódból mit emeltél át, **mit vettél észre, amit nem írt le explicit a feladat**
- Audit log megoldás indoklása
- Frontend stack és UI library választás
- Mit hagytál ki és miért
- AI használta: hol kellett kézzel javítanod vagy elvetned a generált kódot

## AI-ról

Nem gond, de szeretnénk, hogy **értsd is, amit beadsz** —
a follow-up beszélgetésen átvesszük a kódodat, és arra számítunk,
hogy minden döntésedről tudsz beszélni. A komplexitás is ehhez lett belőve, hogy AI támogatást
feltételezünk. Ha teljesen AI nélkül csináltad esetleg, jelezd a readme.md-ben, 
és aszerint nézzük, hogy meddig jutottál.

## Amit **nem** értékelünk

- Production-grade observability
- teszt esetek hiánya
- Valódi LDAP integráció (a mock interface elég)
- CI/CD pipeline javaslat (szóban kérdezünk rá külön)

## Értékelési szempontok

**Backend (20 pont)**
| Szempont | Max |
|----------|-----|
| `docker compose up` indul                               | 4 |
| Go viselkedés megőrzése (NBSP parsing, idempotencia)    | 4 |
| Symfony architektúra (DI, service réteg, DTO, Security) | 4 |
| Audit log megoldás minősége                             | 3 |
| Státusz kezelés + tranzakcionalitás 	                   | 3 |
| Redis használat (login log race)                        | 2 |

**Frontend (10 pont)**
| Szempont | Max |
|----------|-----|
| 4 oldal mind működik                  | 4 |
| Type safety, komponens-szervezés      | 2 |
| Szerepkör-alapú UI (admin gombok)     | 2 |
| Vizuális megjelenés és UX             | 2 |

**Kommunikáció (5 pont)**
| Szempont | Max |
|----------|-----|
| README minőség                              | 3 |
| Commit history (nem 1 darab "first commit") | 2 |

## Beadás

**Public GitHub vagy GitLab repó linkjét küldd vissza emailben.**
A `legacy-go/`, `seed-data/`, `db/` mappákat hagyd bent változatlanul.

A repó commit-történetében szeretnénk látni a munkamenetet — egyetlen
"first commit" nem ideális.

Bármi nem világos? Írj nyugodtan a feladat kiküldőjének — kérdezni
nem hátrány.