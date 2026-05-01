#!/bin/sh
# Mindkét DB-ben létrehozza ugyanazt az alapsémát.
# A Go szerviz auctions_legacy-t használja, a Symfony auctions-t.
set -eu

SCHEMA_SQL='
CREATE TABLE IF NOT EXISTS auctions (
    id          BIGSERIAL PRIMARY KEY,
    case_no     TEXT        NOT NULL UNIQUE,
    debtor      TEXT        NOT NULL,
    starts_at   TIMESTAMPTZ NOT NULL,
    status      TEXT        NOT NULL DEFAULT '"'"'pending'"'"',
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS assets (
    id           BIGSERIAL PRIMARY KEY,
    auction_id   BIGINT      NOT NULL REFERENCES auctions(id) ON DELETE CASCADE,
    title        TEXT        NOT NULL,
    description  TEXT,
    min_price    BIGINT      NOT NULL,
    category     TEXT,
    created_at   TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_assets_auction_id  ON assets(auction_id);
CREATE INDEX IF NOT EXISTS idx_auctions_starts_at ON auctions(starts_at);
'

echo "Séma létrehozása auctions_legacy adatbázisban..."
psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname auctions_legacy -c "$SCHEMA_SQL"

echo "Séma létrehozása auctions adatbázisban..."
psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname auctions -c "$SCHEMA_SQL"

echo "Mindkét adatbázis sémája kész."
