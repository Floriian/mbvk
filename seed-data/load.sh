#!/bin/sh
# Bejelentkezik kovacs.janos felhasználóval, majd betölti a
# /seed-data/xml/[0-9]*.xml fájlokat a legacy-go /auctions/import
# endpointjára. Ez csak a Go szervizt tölti fel (auctions_legacy DB).
# A Symfony oldali auctions DB üresen marad - oda a jelölt importál.
set -eu

TARGET="${TARGET:-http://legacy-go:8080}"
USERNAME="${USERNAME:-kovacs.janos}"
PASSWORD="${PASSWORD:-Kovacs123!}"

echo "loader: várakozás $TARGET/health-re..."
i=0
until curl -sf "$TARGET/health" > /dev/null; do
    i=$((i + 1))
    if [ "$i" -gt 60 ]; then
        echo "loader: timeout $TARGET" >&2
        exit 1
    fi
    sleep 1
done

echo "loader: bejelentkezés mint $USERNAME..."
LOGIN_RESP=$(curl -sf -X POST -H 'Content-Type: application/json' \
    -d "{\"username\":\"$USERNAME\",\"password\":\"$PASSWORD\"}" \
    "$TARGET/auth/login") || {
    echo "loader: login sikertelen" >&2
    exit 1
}
TOKEN=$(echo "$LOGIN_RESP" | sed -n 's/.*"token":"\([^"]*\)".*/\1/p')
if [ -z "$TOKEN" ]; then
    echo "loader: nem találtam tokent a válaszban" >&2
    exit 1
fi
echo "loader: token megszerezve"

ok=0
skipped=0
failed=0
for f in /seed-data/xml/[0-9]*.xml; do
    [ -f "$f" ] || continue
    name=$(basename "$f")
    code=$(curl -s -o /tmp/resp -w '%{http_code}' \
        -X POST \
        -H 'Content-Type: application/xml' \
        -H "Authorization: Bearer $TOKEN" \
        --data-binary "@$f" \
        "$TARGET/auctions/import")
    case "$code" in
        201) echo "  OK   $name"; ok=$((ok + 1)) ;;
        409) echo "  SKIP $name (már importálva)"; skipped=$((skipped + 1)) ;;
        *)   echo "  FAIL $name -> HTTP $code: $(cat /tmp/resp)" >&2; failed=$((failed + 1)) ;;
    esac
done

echo "loader: kész. importálva=$ok kihagyva=$skipped sikertelen=$failed"
