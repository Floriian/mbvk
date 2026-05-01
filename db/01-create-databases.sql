-- Két különálló adatbázis:
--   auctions_legacy: a Go szerviz használja, a 10 minta XML-lel feltöltve
--   auctions:        a Symfony port használja, csak séma, üres adattal

CREATE DATABASE auctions_legacy;
CREATE DATABASE auctions;

GRANT ALL PRIVILEGES ON DATABASE auctions_legacy TO app;
GRANT ALL PRIVILEGES ON DATABASE auctions TO app;
