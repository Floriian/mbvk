-- Alapséma. A legacy Go szerviz ezt használja.
-- Az audit_log táblát és a hozzá tartozó megoldást a Symfony port
-- során kell kialakítani.

CREATE TABLE auctions (
    id          BIGSERIAL PRIMARY KEY,
    case_no     TEXT        NOT NULL UNIQUE,
    debtor      TEXT        NOT NULL,
    starts_at   TIMESTAMPTZ NOT NULL,
    status      TEXT        NOT NULL DEFAULT 'pending',
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE assets (
    id           BIGSERIAL PRIMARY KEY,
    auction_id   BIGINT      NOT NULL REFERENCES auctions(id) ON DELETE CASCADE,
    title        TEXT        NOT NULL,
    description  TEXT,
    min_price    BIGINT      NOT NULL,  -- fillér (HUF * 100)
    category     TEXT,
    created_at   TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX idx_assets_auction_id  ON assets(auction_id);
CREATE INDEX idx_auctions_starts_at ON auctions(starts_at);
