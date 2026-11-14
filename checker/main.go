package main

import (
	"bytes"
	"context"
	"crypto/rand"
	"crypto/tls"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"log"
	"net"
	"net/http"
	"net/url"
	"os"
	"os/signal"
	"path/filepath"
	"strconv"
	"strings"
	"sync"
	"syscall"
	"time"
)

var checkerVersion = "1.0.0"

type config struct {
	baseURL        string
	apiKey         string
	guid           string
	interval       time.Duration
	timeout        time.Duration
	concurrency    int
	userAgent      string
	checkerName    string
	checkSSL       bool
	checkDomain    bool
	domainInterval time.Duration
}

type service struct {
	ID   int    `json:"id"`
	Slug string `json:"slug"`
	Name string `json:"name"`
}

type servicesResponse struct {
	Services []service `json:"services"`
	SSL      bool      `json:"ssl"`
	Domain   bool      `json:"domain"`
}

type metric struct {
	Name  string  `json:"name"`
	Value float64 `json:"value"`
	Type  string  `json:"type"`
}

type ingestRequest struct {
	Service string   `json:"service"`
	Metrics []metric `json:"metrics"`
}

type checkResult struct {
	service      service
	status       int
	responseTime float64
	up           bool
	ok           bool
	scheme       string
}

type sslInfo struct {
	hasCertificate bool
	validFrom      *string
	validTo        *string
}

type sslPayload struct {
	HasCertificate bool    `json:"has_certificate"`
	ValidFrom      *string `json:"valid_from,omitempty"`
	ValidTo        *string `json:"valid_to,omitempty"`
}

type domainInfo struct {
	registeredAt *string
	expiresAt    *string
}

type domainPayload struct {
	RegisteredAt *string `json:"registered_at"`
	ExpiresAt    *string `json:"expires_at"`
}

type domainCacheEntry struct {
	info      *domainInfo
	nextCheck time.Time
}

var domainCache = struct {
	sync.Mutex
	entries map[string]domainCacheEntry
}{entries: make(map[string]domainCacheEntry)}

var whoisServers = map[string]string{
	"ru":       "whois.tcinet.ru",
	"su":       "whois.tcinet.ru",
	"xn--p1ai": "whois.tcinet.ru",
	"com":      "whois.verisign-grs.com",
	"net":      "whois.verisign-grs.com",
	"org":      "whois.pir.org",
	"io":       "whois.nic.io",
	"dev":      "whois.nic.google",
	"app":      "whois.nic.google",
	"me":       "whois.nic.me",
	"info":     "whois.afilias.net",
	"biz":      "whois.biz",
	"xyz":      "whois.nic.xyz",
	"co":       "whois.nic.co",
	"uk":       "whois.nic.uk",
	"de":       "whois.denic.de",
}

type metadataRequest struct {
	Domain *domainPayload `json:"domain,omitempty"`
	SSL    *sslPayload    `json:"ssl,omitempty"`
}

func main() {
	loadEnv()

	guid, err := ensureCheckerGUID()
	if err != nil {
		log.Fatalf("guid: %v", err)
	}

	cfg, err := loadConfig()

	if err != nil {
		log.Fatalf("config: %v", err)
	}

	cfg.guid = guid

	ctx, stop := signal.NotifyContext(context.Background(), os.Interrupt, syscall.SIGTERM)
	defer stop()

	run(ctx, cfg)
}

func loadConfig() (config, error) {
	cfg := config{
		baseURL:        strings.TrimRight(env("MONITOR_BASE_URL", "http://127.0.0.1:7060"), "/"),
		apiKey:         strings.TrimSpace(os.Getenv("MONITOR_API_KEY")),
		interval:       60 * time.Second,
		timeout:        10 * time.Second,
		concurrency:    5,
		userAgent:      env("CHECK_USER_AGENT", "2adm-checker/1.0"),
		checkerName:    env("CHECKER_NAME", "site-checker"),
		checkSSL:       boolEnv("CHECK_SSL", false),
		checkDomain:    boolEnv("CHECK_DOMAIN", false),
		domainInterval: 24 * time.Hour,
	}

	if cfg.apiKey == "" {
		return cfg, errors.New("MONITOR_API_KEY is required")
	}

	var err error

	if cfg.interval, err = durationEnv("CHECK_INTERVAL", cfg.interval); err != nil {
		return cfg, err
	}

	if cfg.timeout, err = durationEnv("CHECK_TIMEOUT", cfg.timeout); err != nil {
		return cfg, err
	}

	cfg.concurrency = intEnv("CHECK_CONCURRENCY", cfg.concurrency)

	if cfg.concurrency < 1 {
		cfg.concurrency = 1
	}

	if cfg.domainInterval, err = durationEnv("CHECK_DOMAIN_INTERVAL", cfg.domainInterval); err != nil {
		return cfg, err
	}

	if cfg.domainInterval <= 0 {
		cfg.domainInterval = 24 * time.Hour
	}

	return cfg, nil
}

func run(ctx context.Context, cfg config) {
	if cfg.guid == "" {
		log.Fatalf("checker guid is empty")
	}

	apiClient := &http.Client{Timeout: 15 * time.Second}
	checkClient := &http.Client{
		Timeout: cfg.timeout,
		CheckRedirect: func(req *http.Request, via []*http.Request) error {
			if len(via) >= 5 {
				return http.ErrUseLastResponse
			}

			return nil
		},
	}

	log.Printf(
		"checker started: name=%s guid=%s base=%s interval=%s timeout=%s concurrency=%d",
		cfg.checkerName,
		cfg.guid,
		cfg.baseURL,
		cfg.interval,
		cfg.timeout,
		cfg.concurrency,
	)

	checkCycle(ctx, cfg, apiClient, checkClient)

	ticker := time.NewTicker(cfg.interval)
	defer ticker.Stop()

	for {
		select {
		case <-ctx.Done():
			log.Printf("checker stopped")
			return
		case <-ticker.C:
			checkCycle(ctx, cfg, apiClient, checkClient)
		}
	}
}

func checkCycle(ctx context.Context, cfg config, apiClient, checkClient *http.Client) {
	if err := sendHeartbeat(ctx, apiClient, cfg); err != nil {
		log.Printf("heartbeat: %v", err)
	}

	services, sslEnabled, domainEnabled, err := fetchServices(ctx, apiClient, cfg)

	if err != nil {
		log.Printf("fetch services: %v", err)
		return
	}

	if cfg.checkSSL && !sslEnabled {
		log.Printf("ssl checks disabled by the API response")
	}

	if cfg.checkDomain && !domainEnabled {
		log.Printf("domain checks disabled by the API response")
	}

	if len(services) == 0 {
		log.Printf("no services to check")
		return
	}

	log.Printf("checking %d services", len(services))

	var (
		wg       sync.WaitGroup
		mu       sync.Mutex
		okCount  int
		failures int
	)

	sem := make(chan struct{}, cfg.concurrency)

	for _, svc := range services {
		svc := svc
		wg.Add(1)

		go func() {
			defer wg.Done()

			select {
			case sem <- struct{}{}:
				defer func() { <-sem }()
			case <-ctx.Done():
				return
			}

			result := checkService(ctx, checkClient, cfg, svc)
			metricsErr := sendMetrics(ctx, apiClient, cfg, result)

			var domain *domainInfo

			if domainEnabled {
				domain = lookupDomain(ctx, apiClient, cfg, svc.Slug)
			}

			var ssl *sslInfo

			if sslEnabled {
				info, err := fetchCertificate(cfg, svc.Slug)

				if err != nil {
					log.Printf("ssl %s: %v", svc.Slug, err)
				} else {
					ssl = &info
				}
			}

			if domain != nil || ssl != nil {
				if err := sendMetadata(ctx, apiClient, cfg, svc.Slug, domain, ssl); err != nil {
					log.Printf("metadata %s: %v", svc.Slug, err)
				}
			}

			if metricsErr != nil {
				log.Printf("ingest %s: %v", svc.Slug, metricsErr)

				mu.Lock()
				failures++
				mu.Unlock()

				return
			}

			log.Printf(
				"%s: status=%d time=%.1fms up=%t ok=%t scheme=%s",
				svc.Slug,
				result.status,
				result.responseTime,
				result.up,
				result.ok,
				result.scheme,
			)

			mu.Lock()

			if result.ok {
				okCount++
			} else {
				failures++
			}

			mu.Unlock()
		}()
	}

	wg.Wait()

	log.Printf("cycle finished: %d checked, %d ok, %d problems", len(services), okCount, failures)
}

func newAPIRequest(ctx context.Context, method, endpoint string, body []byte, cfg config) (*http.Request, error) {
	var reader io.Reader

	if body != nil {
		reader = bytes.NewReader(body)
	}

	req, err := http.NewRequestWithContext(ctx, method, endpoint, reader)

	if err != nil {
		return nil, err
	}

	req.Header.Set("Accept", "application/json")
	req.Header.Set("User-Agent", cfg.userAgent)
	req.Header.Set("X-Checker-Guid", cfg.guid)
	req.Header.Set("X-Api-Key", cfg.apiKey)

	if body != nil {
		req.Header.Set("Content-Type", "application/json")
	}

	return req, nil
}

func fetchServices(ctx context.Context, client *http.Client, cfg config) ([]service, bool, bool, error) {
	query := url.Values{}

	if cfg.checkSSL {
		query.Set("ssl", "true")
	}

	if cfg.checkDomain {
		query.Set("domain", "true")
	}

	servicesURL := cfg.baseURL + "/api/v1/services"

	if len(query) > 0 {
		servicesURL += "?" + query.Encode()
	}

	req, err := newAPIRequest(ctx, http.MethodGet, servicesURL, nil, cfg)

	if err != nil {
		return nil, false, false, err
	}

	res, err := client.Do(req)

	if err != nil {
		return nil, false, false, err
	}

	defer res.Body.Close()

	if res.StatusCode < 200 || res.StatusCode >= 300 {
		body, _ := io.ReadAll(io.LimitReader(res.Body, 4096))
		return nil, false, false, fmt.Errorf("services returned %d: %s", res.StatusCode, strings.TrimSpace(string(body)))
	}

	var payload servicesResponse

	if err := json.NewDecoder(io.LimitReader(res.Body, 4<<20)).Decode(&payload); err != nil {
		return nil, false, false, fmt.Errorf("decode services: %w", err)
	}

	return payload.Services, payload.SSL, payload.Domain, nil
}

func checkService(ctx context.Context, client *http.Client, cfg config, svc service) checkResult {
	status, elapsed, err := doCheck(ctx, client, cfg, "https://"+svc.Slug)
	scheme := "https"

	if err != nil {
		status, elapsed, err = doCheck(ctx, client, cfg, "http://"+svc.Slug)
		scheme = "http"
	}

	if err != nil {
		log.Printf("check %s: %v", svc.Slug, err)

		return checkResult{
			service:      svc,
			status:       0,
			responseTime: elapsed,
			up:           false,
			ok:           false,
			scheme:       scheme,
		}
	}

	return checkResult{
		service:      svc,
		status:       status,
		responseTime: elapsed,
		up:           true,
		ok:           status >= 200 && status < 400,
		scheme:       scheme,
	}
}

func doCheck(ctx context.Context, client *http.Client, cfg config, url string) (int, float64, error) {
	req, err := http.NewRequestWithContext(ctx, http.MethodGet, url, nil)

	if err != nil {
		return 0, 0, err
	}

	req.Header.Set("User-Agent", cfg.userAgent)
	req.Header.Set("Accept", "*/*")

	started := time.Now()
	res, err := client.Do(req)
	elapsed := float64(time.Since(started).Microseconds()) / 1000.0

	if err != nil {
		return 0, elapsed, err
	}

	defer res.Body.Close()
	_, _ = io.Copy(io.Discard, io.LimitReader(res.Body, 1<<20))

	return res.StatusCode, elapsed, nil
}

type heartbeatRequest struct {
	Name    string `json:"name"`
	GUID    string `json:"guid"`
	Host    string `json:"host"`
	Version string `json:"version"`
}

func sendHeartbeat(ctx context.Context, client *http.Client, cfg config) error {
	hostname, _ := os.Hostname()

	payload := heartbeatRequest{
		Name:    cfg.checkerName,
		GUID:    cfg.guid,
		Host:    hostname,
		Version: checkerVersion,
	}

	body, err := json.Marshal(payload)

	if err != nil {
		return err
	}

	req, err := newAPIRequest(ctx, http.MethodPost, cfg.baseURL+"/api/v1/checkers/heartbeat", body, cfg)

	if err != nil {
		return err
	}

	res, err := client.Do(req)

	if err != nil {
		return err
	}

	defer res.Body.Close()

	if res.StatusCode < 200 || res.StatusCode >= 300 {
		data, _ := io.ReadAll(io.LimitReader(res.Body, 4096))
		return fmt.Errorf("heartbeat returned %d: %s", res.StatusCode, strings.TrimSpace(string(data)))
	}

	_, _ = io.Copy(io.Discard, res.Body)

	return nil
}

func sendMetrics(ctx context.Context, client *http.Client, cfg config, result checkResult) error {
	up := 0.0
	if result.up {
		up = 1
	}

	ok := 0.0
	if result.ok {
		ok = 1
	}

	payload := ingestRequest{
		Service: result.service.Slug,
		Metrics: []metric{
			{Name: "http.status", Value: float64(result.status), Type: "gauge"},
			{Name: "response.time", Value: result.responseTime, Type: "gauge"},
			{Name: "check.up", Value: up, Type: "gauge"},
			{Name: "http.ok", Value: ok, Type: "gauge"},
		},
	}

	body, err := json.Marshal(payload)

	if err != nil {
		return err
	}

	req, err := newAPIRequest(ctx, http.MethodPost, cfg.baseURL+"/api/v1/metrics", body, cfg)

	if err != nil {
		return err
	}

	res, err := client.Do(req)

	if err != nil {
		return err
	}

	defer res.Body.Close()

	if res.StatusCode < 200 || res.StatusCode >= 300 {
		data, _ := io.ReadAll(io.LimitReader(res.Body, 4096))
		return fmt.Errorf("ingest returned %d: %s", res.StatusCode, strings.TrimSpace(string(data)))
	}

	_, _ = io.Copy(io.Discard, res.Body)

	return nil
}

func fetchCertificate(cfg config, host string) (sslInfo, error) {
	dialer := &net.Dialer{Timeout: cfg.timeout}

	conn, err := tls.DialWithDialer(dialer, "tcp", host+":443", &tls.Config{
		ServerName:         host,
		InsecureSkipVerify: true,
	})

	if err != nil {
		return sslInfo{}, err
	}

	defer conn.Close()

	certificates := conn.ConnectionState().PeerCertificates

	if len(certificates) == 0 {
		return sslInfo{}, errors.New("no peer certificate")
	}

	validFrom := certificates[0].NotBefore.UTC().Format(time.RFC3339)
	validTo := certificates[0].NotAfter.UTC().Format(time.RFC3339)

	return sslInfo{
		hasCertificate: true,
		validFrom:      &validFrom,
		validTo:        &validTo,
	}, nil
}

func sendMetadata(ctx context.Context, client *http.Client, cfg config, slug string, domain *domainInfo, ssl *sslInfo) error {
	payload := metadataRequest{}

	if domain != nil {
		payload.Domain = &domainPayload{
			RegisteredAt: domain.registeredAt,
			ExpiresAt:    domain.expiresAt,
		}
	}

	if ssl != nil {
		payload.SSL = &sslPayload{
			HasCertificate: ssl.hasCertificate,
			ValidFrom:      ssl.validFrom,
			ValidTo:        ssl.validTo,
		}
	}

	if payload.Domain == nil && payload.SSL == nil {
		return nil
	}

	body, err := json.Marshal(payload)

	if err != nil {
		return err
	}

	endpoint := cfg.baseURL + "/api/v1/sites/" + url.PathEscape(slug) + "/metadata"

	req, err := newAPIRequest(ctx, http.MethodPut, endpoint, body, cfg)

	if err != nil {
		return err
	}

	res, err := client.Do(req)

	if err != nil {
		return err
	}

	defer res.Body.Close()

	if res.StatusCode < 200 || res.StatusCode >= 300 {
		data, _ := io.ReadAll(io.LimitReader(res.Body, 4096))
		return fmt.Errorf("metadata returned %d: %s", res.StatusCode, strings.TrimSpace(string(data)))
	}

	_, _ = io.Copy(io.Discard, res.Body)

	return nil
}

func lookupDomain(ctx context.Context, client *http.Client, cfg config, host string) *domainInfo {
	domainCache.Lock()
	entry, ok := domainCache.entries[host]
	domainCache.Unlock()

	if ok && time.Now().Before(entry.nextCheck) {
		return entry.info
	}

	info, err := fetchDomainInfo(ctx, client, cfg, host)

	if err != nil {
		log.Printf("domain %s: %v", host, err)
	}

	ttl := cfg.domainInterval

	if err != nil && ttl > time.Hour {
		ttl = time.Hour
	}

	domainCache.Lock()
	domainCache.entries[host] = domainCacheEntry{info: info, nextCheck: time.Now().Add(ttl)}
	domainCache.Unlock()

	return info
}

func fetchDomainInfo(ctx context.Context, client *http.Client, cfg config, host string) (*domainInfo, error) {
	info, rdapErr := fetchRdapInfo(ctx, client, cfg, host)

	if rdapErr == nil && info != nil {
		return info, nil
	}

	info, whoisErr := fetchWhoisInfo(cfg, host)

	if whoisErr == nil && info != nil {
		return info, nil
	}

	return nil, fmt.Errorf("rdap: %v; whois: %v", rdapErr, whoisErr)
}

func fetchWhoisInfo(cfg config, host string) (*domainInfo, error) {
	server := whoisServerFor(host)

	if server == "" {
		return nil, fmt.Errorf("no whois server for %s", host)
	}

	conn, err := net.DialTimeout("tcp", server+":43", cfg.timeout)

	if err != nil {
		return nil, err
	}

	defer conn.Close()
	_ = conn.SetDeadline(time.Now().Add(cfg.timeout))

	if _, err := fmt.Fprintf(conn, "%s\r\n", host); err != nil {
		return nil, err
	}

	data, err := io.ReadAll(io.LimitReader(conn, 1<<20))

	if err != nil {
		return nil, err
	}

	info := &domainInfo{}

	for _, line := range strings.Split(string(data), "\n") {
		line = strings.TrimSpace(line)

		if line == "" || strings.HasPrefix(line, "%") || strings.HasPrefix(line, "#") {
			continue
		}

		key, value, ok := strings.Cut(line, ":")

		if !ok {
			continue
		}

		key = strings.ToLower(strings.TrimSpace(key))
		value = strings.TrimSpace(value)

		switch key {
		case "created", "creation date", "registered on", "registration time", "domain registration date":
			if info.registeredAt == nil {
				info.registeredAt = parseWhoisDate(value)
			}
		case "paid-till", "registry expiry date", "expiration date", "expiry date", "free-date":
			if info.expiresAt == nil {
				info.expiresAt = parseWhoisDate(value)
			}
		}
	}

	if info.registeredAt == nil && info.expiresAt == nil {
		return nil, errors.New("whois response has no registration dates")
	}

	return info, nil
}

func whoisServerFor(host string) string {
	parts := strings.Split(host, ".")

	if len(parts) < 2 {
		return ""
	}

	return whoisServers[strings.ToLower(parts[len(parts)-1])]
}

func parseWhoisDate(value string) *string {
	value = strings.TrimSpace(value)

	if value == "" {
		return nil
	}

	layouts := []string{
		time.RFC3339,
		"2006-01-02T15:04:05",
		"2006-01-02 15:04:05",
		"2006-01-02",
		"02.01.2006",
	}

	for _, layout := range layouts {
		if parsed, err := time.Parse(layout, value); err == nil {
			normalized := parsed.UTC().Format(time.RFC3339)

			return &normalized
		}
	}

	return &value
}

func fetchRdapInfo(ctx context.Context, client *http.Client, cfg config, host string) (*domainInfo, error) {
	endpoint := "https://rdap.org/domain/" + url.PathEscape(host)

	req, err := http.NewRequestWithContext(ctx, http.MethodGet, endpoint, nil)

	if err != nil {
		return nil, err
	}

	req.Header.Set("Accept", "application/rdap+json, application/json")
	req.Header.Set("User-Agent", cfg.userAgent)

	res, err := client.Do(req)

	if err != nil {
		return nil, err
	}

	defer res.Body.Close()

	if res.StatusCode < 200 || res.StatusCode >= 300 {
		data, _ := io.ReadAll(io.LimitReader(res.Body, 4096))
		return nil, fmt.Errorf("rdap returned %d: %s", res.StatusCode, strings.TrimSpace(string(data)))
	}

	var payload struct {
		Events []struct {
			Action string `json:"eventAction"`
			Date   string `json:"eventDate"`
		} `json:"events"`
	}

	if err := json.NewDecoder(io.LimitReader(res.Body, 4<<20)).Decode(&payload); err != nil {
		return nil, fmt.Errorf("decode rdap: %w", err)
	}

	info := &domainInfo{}

	for _, event := range payload.Events {
		switch strings.ToLower(event.Action) {
		case "registration":
			info.registeredAt = normalizeDate(event.Date)
		case "expiration":
			info.expiresAt = normalizeDate(event.Date)
		}
	}

	if info.registeredAt == nil && info.expiresAt == nil {
		return nil, errors.New("rdap response has no registration dates")
	}

	return info, nil
}

func normalizeDate(value string) *string {
	if value == "" {
		return nil
	}

	if parsed, err := time.Parse(time.RFC3339, value); err == nil {
		normalized := parsed.UTC().Format(time.RFC3339)

		return &normalized
	}

	return &value
}

func env(name, fallback string) string {
	if value := strings.TrimSpace(os.Getenv(name)); value != "" {
		return value
	}

	return fallback
}

func durationEnv(name string, fallback time.Duration) (time.Duration, error) {
	value := strings.TrimSpace(os.Getenv(name))

	if value == "" {
		return fallback, nil
	}

	if seconds, err := strconv.Atoi(value); err == nil {
		return time.Duration(seconds) * time.Second, nil
	}

	duration, err := time.ParseDuration(value)

	if err != nil {
		return fallback, fmt.Errorf("%s: %w", name, err)
	}

	return duration, nil
}

func intEnv(name string, fallback int) int {
	value := strings.TrimSpace(os.Getenv(name))

	if value == "" {
		return fallback
	}

	parsed, err := strconv.Atoi(value)

	if err != nil {
		return fallback
	}

	return parsed
}

func boolEnv(name string, fallback bool) bool {
	value := strings.ToLower(strings.TrimSpace(os.Getenv(name)))

	if value == "" {
		return fallback
	}

	switch value {
	case "1", "true", "yes", "on":
		return true
	case "0", "false", "no", "off":
		return false
	default:
		return fallback
	}
}

func ensureCheckerGUID() (string, error) {
	if value := strings.TrimSpace(os.Getenv("CHECKER_GUID")); value != "" {
		return value, nil
	}

	path := strings.TrimSpace(os.Getenv("CHECKER_GUID_FILE"))

	if path == "" {
		exe, err := os.Executable()
		if err != nil {
			return "", fmt.Errorf("locate executable: %w", err)
		}

		if resolved, err := filepath.EvalSymlinks(exe); err == nil {
			exe = resolved
		}

		path = filepath.Join(filepath.Dir(exe), "checker.guid")
	}

	if data, err := os.ReadFile(path); err == nil {
		if value := strings.TrimSpace(string(data)); value != "" {
			return value, nil
		}
	}

	guid, err := newGUID()
	if err != nil {
		return "", err
	}

	if err := os.WriteFile(path, []byte(guid+"\n"), 0o600); err != nil {
		log.Printf("guid: cannot persist to %s: %v (set CHECKER_GUID to keep it stable)", path, err)
	}

	return guid, nil
}

func newGUID() (string, error) {
	raw := make([]byte, 16)

	if _, err := rand.Read(raw); err != nil {
		return "", fmt.Errorf("generate guid: %w", err)
	}

	raw[6] = (raw[6] & 0x0f) | 0x40
	raw[8] = (raw[8] & 0x3f) | 0x80

	return fmt.Sprintf(
		"%x-%x-%x-%x-%x",
		raw[0:4],
		raw[4:6],
		raw[6:8],
		raw[8:10],
		raw[10:16],
	), nil
}

func loadEnv() {
	if custom := strings.TrimSpace(os.Getenv("CHECKER_ENV")); custom != "" {
		loadDotEnv(custom)
		return
	}

	for _, path := range envFileCandidates() {
		loadDotEnv(path)
	}
}

func envFileCandidates() []string {
	candidates := []string{}

	if exe, err := os.Executable(); err == nil {
		if resolved, err := filepath.EvalSymlinks(exe); err == nil {
			exe = resolved
		}

		dir := filepath.Dir(exe)
		candidates = append(candidates,
			filepath.Join(dir, "checker.env"),
			filepath.Join(dir, ".env"),
		)
	}

	candidates = append(candidates, "checker/.env", ".env")

	return candidates
}

func loadDotEnv(path string) {
	data, err := os.ReadFile(path)

	if err != nil {
		return
	}

	for _, line := range strings.Split(string(data), "\n") {
		line = strings.TrimSpace(line)

		if line == "" || strings.HasPrefix(line, "#") {
			continue
		}

		line = strings.TrimPrefix(line, "export ")
		key, value, ok := strings.Cut(line, "=")

		if !ok {
			continue
		}

		key = strings.TrimSpace(key)
		value = strings.Trim(strings.TrimSpace(value), `"'`)

		if key == "" {
			continue
		}

		if _, exists := os.LookupEnv(key); exists {
			continue
		}

		_ = os.Setenv(key, value)
	}
}
