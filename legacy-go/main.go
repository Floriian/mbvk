// legacy-go: auction service.
//
// Endpoints:
//   POST /auth/login           - LDAP-style authentication, returns bearer token
//   GET  /auth/me              - current user info (requires Bearer token)
//   GET  /auth/recent-logins   - last 100 login attempts (admin only)
//   POST /auctions/import      - XML import (requires Bearer token)
//   GET  /auctions             - list auctions (requires Bearer token)
//   GET  /auctions/{id}        - auction detail (requires Bearer token)
//   GET  /health               - health check (public)
//
// Running in production since 2021. Scheduled for migration to Symfony
// as part of backend stack consolidation.
//
// User directory:
//   The "LDAP" lookup is mocked by an in-memory user table. In the
//   real production deployment this is replaced by a bind-and-search
//   against the corporate LDAP. The Symfony port should keep this
//   abstraction (interface + mock implementation) so the LDAP
//   integration can be swapped in cleanly later.
package main

import (
	"context"
	"crypto/rand"
	"crypto/sha256"
	"crypto/subtle"
	"database/sql"
	"encoding/hex"
	"encoding/json"
	"encoding/xml"
	"fmt"
	"io"
	"log"
	"net/http"
	"os"
	"strconv"
	"strings"
	"time"

	_ "github.com/lib/pq"
	"github.com/redis/go-redis/v9"
)

// ---------------------------------------------------------------------------
// Domain types
// ---------------------------------------------------------------------------

type AuctionXML struct {
	XMLName  xml.Name   `xml:"auction"`
	CaseNo   string     `xml:"case_no"`
	Debtor   string     `xml:"debtor"`
	StartsAt string     `xml:"starts_at"`
	Assets   []AssetXML `xml:"assets>asset"`
}

type AssetXML struct {
	Title       string `xml:"title"`
	Description string `xml:"description"`
	MinPrice    string `xml:"min_price"`
	Category    string `xml:"category,attr"`
}

type LoginRequest struct {
	Username string `json:"username"`
	Password string `json:"password"`
}

type LoginResponse struct {
	Token     string    `json:"token"`
	ExpiresAt time.Time `json:"expires_at"`
	User      UserInfo  `json:"user"`
}

type UserInfo struct {
	Username    string   `json:"username"`
	DisplayName string   `json:"display_name"`
	Email       string   `json:"email"`
	Roles       []string `json:"roles"`
}

type LoginEvent struct {
	Timestamp time.Time `json:"timestamp"`
	Username  string    `json:"username"`
	Success   bool      `json:"success"`
	Reason    string    `json:"reason,omitempty"`
	IP        string    `json:"ip"`
	UserAgent string    `json:"user_agent"`
}

// ---------------------------------------------------------------------------
// Mock LDAP user directory
//
// Passwords are stored as sha256(password). In a real LDAP integration
// the bind operation handles credential verification on the server side.
// The Symfony port should preserve the same interface: given username
// and password, return a UserInfo or an error.
// ---------------------------------------------------------------------------

type ldapUser struct {
	username    string
	pwHashHex   string // sha256 hex of the password
	displayName string
	email       string
	roles       []string
}

func sha256hex(s string) string {
	h := sha256.Sum256([]byte(s))
	return hex.EncodeToString(h[:])
}

var mockUserDirectory = []ldapUser{
	// Test credentials - production uses real LDAP.
	{"kovacs.janos", sha256hex("Kovacs123!"), "Kovács János", "kovacs.janos@example.local", []string{"ROLE_USER"}},
	{"szabo.eva", sha256hex("Szabo456!"), "Szabó Éva", "szabo.eva@example.local", []string{"ROLE_USER"}},
	{"admin", sha256hex("AdminPass789!"), "Rendszergazda", "admin@example.local", []string{"ROLE_USER", "ROLE_ADMIN"}},
}

func ldapAuthenticate(username, password string) (UserInfo, error) {
	target := sha256hex(password)
	for _, u := range mockUserDirectory {
		if u.username == username {
			// constant-time compare to avoid timing leaks on the hash
			if subtle.ConstantTimeCompare([]byte(u.pwHashHex), []byte(target)) == 1 {
				return UserInfo{
					Username:    u.username,
					DisplayName: u.displayName,
					Email:       u.email,
					Roles:       u.roles,
				}, nil
			}
			return UserInfo{}, fmt.Errorf("invalid credentials")
		}
	}
	return UserInfo{}, fmt.Errorf("invalid credentials")
}

// ---------------------------------------------------------------------------
// Server
// ---------------------------------------------------------------------------

type Server struct {
	db    *sql.DB
	redis *redis.Client
}

const (
	tokenTTL          = 8 * time.Hour
	tokenKeyPrefix    = "auth:token:"
	loginLogKey       = "auth:events:recent"
	loginLogMaxLength = 100
)

func main() {
	dsn := envOr("DATABASE_URL", "postgres://app:app@postgres:5432/auctions?sslmode=disable")
	db, err := sql.Open("postgres", dsn)
	if err != nil {
		log.Fatalf("db open: %v", err)
	}
	for i := 0; i < 30; i++ {
		if err = db.Ping(); err == nil {
			break
		}
		log.Printf("waiting for db: %v", err)
		time.Sleep(time.Second)
	}
	if err != nil {
		log.Fatalf("db ping: %v", err)
	}

	rdb := redis.NewClient(&redis.Options{Addr: envOr("REDIS_ADDR", "redis:6379")})

	srv := &Server{db: db, redis: rdb}

	mux := http.NewServeMux()
	mux.HandleFunc("/health", srv.handleHealth)
	mux.HandleFunc("/auth/login", srv.handleLogin)
	mux.HandleFunc("/auth/me", srv.requireAuth(srv.handleMe))
	mux.HandleFunc("/auth/recent-logins", srv.requireAuth(srv.requireRole("ROLE_ADMIN", srv.handleRecentLogins)))
	mux.HandleFunc("/auctions/import", srv.requireAuth(srv.handleImport))
	mux.HandleFunc("/auctions", srv.requireAuth(srv.handleAuctionList))
	mux.HandleFunc("/auctions/", srv.requireAuth(srv.handleAuctionDetail))

	addr := envOr("LISTEN_ADDR", ":8080")
	log.Printf("legacy-go listening on %s", addr)
	log.Fatal(http.ListenAndServe(addr, mux))
}

func envOr(k, def string) string {
	if v := os.Getenv(k); v != "" {
		return v
	}
	return def
}

// ---------------------------------------------------------------------------
// Auth middleware
// ---------------------------------------------------------------------------

type ctxKey string

const userCtxKey ctxKey = "user"

func (s *Server) requireAuth(next http.HandlerFunc) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		token := extractBearer(r)
		if token == "" {
			http.Error(w, "missing bearer token", http.StatusUnauthorized)
			return
		}
		raw, err := s.redis.Get(r.Context(), tokenKeyPrefix+token).Result()
		if err == redis.Nil {
			http.Error(w, "invalid or expired token", http.StatusUnauthorized)
			return
		}
		if err != nil {
			http.Error(w, "auth backend unavailable: "+err.Error(), http.StatusServiceUnavailable)
			return
		}
		var u UserInfo
		if err := json.Unmarshal([]byte(raw), &u); err != nil {
			http.Error(w, "corrupt session", http.StatusInternalServerError)
			return
		}
		ctx := context.WithValue(r.Context(), userCtxKey, u)
		next(w, r.WithContext(ctx))
	}
}

func (s *Server) requireRole(role string, next http.HandlerFunc) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		u, ok := r.Context().Value(userCtxKey).(UserInfo)
		if !ok {
			http.Error(w, "unauthenticated", http.StatusUnauthorized)
			return
		}
		for _, ro := range u.Roles {
			if ro == role {
				next(w, r)
				return
			}
		}
		http.Error(w, "forbidden", http.StatusForbidden)
	}
}

func extractBearer(r *http.Request) string {
	h := r.Header.Get("Authorization")
	if !strings.HasPrefix(h, "Bearer ") {
		return ""
	}
	return strings.TrimSpace(strings.TrimPrefix(h, "Bearer "))
}

// ---------------------------------------------------------------------------
// Auth endpoints
// ---------------------------------------------------------------------------

func (s *Server) handleLogin(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		http.Error(w, "method not allowed", http.StatusMethodNotAllowed)
		return
	}
	var req LoginRequest
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		http.Error(w, "bad json: "+err.Error(), http.StatusBadRequest)
		return
	}
	if req.Username == "" || req.Password == "" {
		http.Error(w, "username and password required", http.StatusBadRequest)
		return
	}

	user, authErr := ldapAuthenticate(req.Username, req.Password)
	clientIP := clientIP(r)
	userAgent := r.Header.Get("User-Agent")

	// Log every login attempt - successful or not - to the login event
	// list. The list is capped at the last 100 entries via LTRIM.
	// This logging is best-effort; a Redis failure should not block
	// the login flow itself.
	evt := LoginEvent{
		Timestamp: time.Now().UTC(),
		Username:  req.Username,
		Success:   authErr == nil,
		IP:        clientIP,
		UserAgent: userAgent,
	}
	if authErr != nil {
		evt.Reason = authErr.Error()
	}
	if buf, err := json.Marshal(evt); err == nil {
		ctx, cancel := context.WithTimeout(r.Context(), 2*time.Second)
		defer cancel()
		pipe := s.redis.TxPipeline()
		pipe.LPush(ctx, loginLogKey, buf)
		pipe.LTrim(ctx, loginLogKey, 0, loginLogMaxLength-1)
		if _, err := pipe.Exec(ctx); err != nil {
			log.Printf("warn: login log write failed (non-fatal): %v", err)
		}
	}

	if authErr != nil {
		http.Error(w, "invalid credentials", http.StatusUnauthorized)
		return
	}

	token, err := generateToken()
	if err != nil {
		http.Error(w, "token gen: "+err.Error(), http.StatusInternalServerError)
		return
	}
	userBuf, _ := json.Marshal(user)
	if err := s.redis.Set(r.Context(), tokenKeyPrefix+token, userBuf, tokenTTL).Err(); err != nil {
		http.Error(w, "session store: "+err.Error(), http.StatusServiceUnavailable)
		return
	}

	resp := LoginResponse{
		Token:     token,
		ExpiresAt: time.Now().UTC().Add(tokenTTL),
		User:      user,
	}
	writeJSON(w, http.StatusOK, resp)
}

func (s *Server) handleMe(w http.ResponseWriter, r *http.Request) {
	u := r.Context().Value(userCtxKey).(UserInfo)
	writeJSON(w, http.StatusOK, u)
}

func (s *Server) handleRecentLogins(w http.ResponseWriter, r *http.Request) {
	limit := 50
	if q := r.URL.Query().Get("limit"); q != "" {
		if n, err := strconv.Atoi(q); err == nil && n > 0 && n <= loginLogMaxLength {
			limit = n
		}
	}
	raw, err := s.redis.LRange(r.Context(), loginLogKey, 0, int64(limit-1)).Result()
	if err != nil {
		http.Error(w, "log fetch: "+err.Error(), http.StatusServiceUnavailable)
		return
	}
	out := make([]LoginEvent, 0, len(raw))
	for _, s := range raw {
		var e LoginEvent
		if err := json.Unmarshal([]byte(s), &e); err == nil {
			out = append(out, e)
		}
	}
	writeJSON(w, http.StatusOK, map[string]any{"events": out, "count": len(out)})
}

// ---------------------------------------------------------------------------
// Auction endpoints
// ---------------------------------------------------------------------------

// handleImport:
//   - validates the XML payload
//   - persists auction + assets in a single transaction
//   - rejects duplicate case_no with 409 (NO upsert semantics)
func (s *Server) handleImport(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		http.Error(w, "method not allowed", http.StatusMethodNotAllowed)
		return
	}
	body, err := io.ReadAll(r.Body)
	if err != nil {
		http.Error(w, "read body: "+err.Error(), http.StatusBadRequest)
		return
	}
	var p AuctionXML
	if err := xml.Unmarshal(body, &p); err != nil {
		http.Error(w, "xml parse: "+err.Error(), http.StatusBadRequest)
		return
	}
	if strings.TrimSpace(p.CaseNo) == "" {
		http.Error(w, "case_no required", http.StatusBadRequest)
		return
	}
	if len(p.Assets) == 0 {
		http.Error(w, "at least one asset required", http.StatusBadRequest)
		return
	}
	startsAt, err := time.Parse(time.RFC3339, strings.TrimSpace(p.StartsAt))
	if err != nil {
		http.Error(w, "starts_at invalid: "+err.Error(), http.StatusBadRequest)
		return
	}

	ctx, cancel := context.WithTimeout(r.Context(), 5*time.Second)
	defer cancel()

	tx, err := s.db.BeginTx(ctx, nil)
	if err != nil {
		http.Error(w, "tx begin: "+err.Error(), http.StatusInternalServerError)
		return
	}
	defer func() { _ = tx.Rollback() }()

	var auctionID int64
	err = tx.QueryRowContext(ctx, `
		INSERT INTO auctions (case_no, debtor, starts_at, status)
		VALUES ($1, $2, $3, 'pending')
		RETURNING id
	`, p.CaseNo, p.Debtor, startsAt).Scan(&auctionID)
	if err != nil {
		if strings.Contains(err.Error(), "duplicate key") {
			http.Error(w, "auction with this case_no already exists", http.StatusConflict)
			return
		}
		http.Error(w, "insert auction: "+err.Error(), http.StatusInternalServerError)
		return
	}

	for _, a := range p.Assets {
		minPrice, err := normalizeAmount(a.MinPrice)
		if err != nil {
			http.Error(w, fmt.Sprintf("asset %q: min_price invalid: %v", a.Title, err), http.StatusBadRequest)
			return
		}
		if _, err := tx.ExecContext(ctx, `
			INSERT INTO assets (auction_id, title, description, min_price, category)
			VALUES ($1, $2, $3, $4, $5)
		`, auctionID, a.Title, a.Description, minPrice, a.Category); err != nil {
			http.Error(w, "insert asset: "+err.Error(), http.StatusInternalServerError)
			return
		}
	}

	if err := tx.Commit(); err != nil {
		http.Error(w, "tx commit: "+err.Error(), http.StatusInternalServerError)
		return
	}

	writeJSON(w, http.StatusCreated, map[string]any{"id": auctionID, "case_no": p.CaseNo})
}

func (s *Server) handleAuctionList(w http.ResponseWriter, r *http.Request) {
	rows, err := s.db.QueryContext(r.Context(), `
		SELECT a.id, a.case_no, a.debtor, a.starts_at, a.status, COUNT(s.id) AS asset_count
		FROM auctions a
		LEFT JOIN assets s ON s.auction_id = a.id
		GROUP BY a.id
		ORDER BY a.starts_at DESC
		LIMIT 100
	`)
	if err != nil {
		http.Error(w, "query: "+err.Error(), http.StatusInternalServerError)
		return
	}
	defer rows.Close()

	type row struct {
		ID         int64     `json:"id"`
		CaseNo     string    `json:"case_no"`
		Debtor     string    `json:"debtor"`
		StartsAt   time.Time `json:"starts_at"`
		Status     string    `json:"status"`
		AssetCount int       `json:"asset_count"`
	}
	out := []row{}
	for rows.Next() {
		var r row
		if err := rows.Scan(&r.ID, &r.CaseNo, &r.Debtor, &r.StartsAt, &r.Status, &r.AssetCount); err != nil {
			http.Error(w, "scan: "+err.Error(), http.StatusInternalServerError)
			return
		}
		out = append(out, r)
	}
	writeJSON(w, http.StatusOK, map[string]any{"items": out})
}

func (s *Server) handleAuctionDetail(w http.ResponseWriter, r *http.Request) {
	idStr := strings.TrimPrefix(r.URL.Path, "/auctions/")
	id, err := strconv.ParseInt(idStr, 10, 64)
	if err != nil {
		http.Error(w, "bad id", http.StatusBadRequest)
		return
	}
	var auction struct {
		ID       int64     `json:"id"`
		CaseNo   string    `json:"case_no"`
		Debtor   string    `json:"debtor"`
		StartsAt time.Time `json:"starts_at"`
		Status   string    `json:"status"`
	}
	err = s.db.QueryRowContext(r.Context(), `
		SELECT id, case_no, debtor, starts_at, status FROM auctions WHERE id = $1
	`, id).Scan(&auction.ID, &auction.CaseNo, &auction.Debtor, &auction.StartsAt, &auction.Status)
	if err == sql.ErrNoRows {
		http.Error(w, "not found", http.StatusNotFound)
		return
	}
	if err != nil {
		http.Error(w, "query: "+err.Error(), http.StatusInternalServerError)
		return
	}
	rows, err := s.db.QueryContext(r.Context(), `
		SELECT id, title, description, min_price, category FROM assets WHERE auction_id = $1 ORDER BY id
	`, id)
	if err != nil {
		http.Error(w, "query: "+err.Error(), http.StatusInternalServerError)
		return
	}
	defer rows.Close()
	type asset struct {
		ID          int64  `json:"id"`
		Title       string `json:"title"`
		Description string `json:"description"`
		MinPrice    int64  `json:"min_price"`
		Category    string `json:"category"`
	}
	assets := []asset{}
	for rows.Next() {
		var a asset
		if err := rows.Scan(&a.ID, &a.Title, &a.Description, &a.MinPrice, &a.Category); err != nil {
			http.Error(w, "scan: "+err.Error(), http.StatusInternalServerError)
			return
		}
		assets = append(assets, a)
	}
	writeJSON(w, http.StatusOK, map[string]any{"auction": auction, "assets": assets})
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

func (s *Server) handleHealth(w http.ResponseWriter, r *http.Request) {
	if err := s.db.Ping(); err != nil {
		http.Error(w, "db down", http.StatusServiceUnavailable)
		return
	}
	if err := s.redis.Ping(r.Context()).Err(); err != nil {
		http.Error(w, "redis down", http.StatusServiceUnavailable)
		return
	}
	_, _ = w.Write([]byte("ok"))
}

func writeJSON(w http.ResponseWriter, status int, body any) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(status)
	_ = json.NewEncoder(w).Encode(body)
}

func generateToken() (string, error) {
	b := make([]byte, 32)
	if _, err := rand.Read(b); err != nil {
		return "", err
	}
	return hex.EncodeToString(b), nil
}

func clientIP(r *http.Request) string {
	if xf := r.Header.Get("X-Forwarded-For"); xf != "" {
		if i := strings.Index(xf, ","); i >= 0 {
			return strings.TrimSpace(xf[:i])
		}
		return strings.TrimSpace(xf)
	}
	host := r.RemoteAddr
	if i := strings.LastIndex(host, ":"); i >= 0 {
		return host[:i]
	}
	return host
}

// normalizeAmount parses Hungarian-formatted monetary strings into
// integer fillér (HUF * 100). Examples:
//
//	"1 250 000,00 Ft" -> 125000000
//	"500000"          -> 50000000
//	"12 345,5"        -> 1234550
//
// Rules:
//   - strip trailing " Ft" / " ft" (case-insensitive)
//   - remove all whitespace, including NBSP (U+00A0)
//   - comma is decimal separator; max 2 fractional digits
//   - integer storage (BIGINT) to avoid float rounding on money
func normalizeAmount(raw string) (int64, error) {
	s := strings.TrimSpace(raw)
	if lower := strings.ToLower(s); strings.HasSuffix(lower, "ft") {
		s = s[:len(s)-2]
	}
	s = strings.Map(func(r rune) rune {
		if r == ' ' || r == '\t' || r == '\u00A0' {
			return -1
		}
		return r
	}, s)
	s = strings.TrimSpace(s)
	if s == "" {
		return 0, fmt.Errorf("empty amount")
	}
	parts := strings.Split(s, ",")
	var intPart, fracPart string
	switch len(parts) {
	case 1:
		intPart, fracPart = parts[0], "00"
	case 2:
		intPart, fracPart = parts[0], parts[1]
		if len(fracPart) > 2 {
			return 0, fmt.Errorf("too many decimals in %q", raw)
		}
		if len(fracPart) == 1 {
			fracPart += "0"
		}
	default:
		return 0, fmt.Errorf("unexpected format %q", raw)
	}
	ip, err := strconv.ParseInt(intPart, 10, 64)
	if err != nil {
		return 0, fmt.Errorf("int part: %w", err)
	}
	fp, err := strconv.ParseInt(fracPart, 10, 64)
	if err != nil {
		return 0, fmt.Errorf("frac part: %w", err)
	}
	return ip*100 + fp, nil
}
