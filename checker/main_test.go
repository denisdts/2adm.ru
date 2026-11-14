package main

import (
	"net/http"
	"net/http/httptest"
	"net/netip"
	"testing"
	"time"
)

func TestIsPublicAddr(t *testing.T) {
	tests := []struct {
		addr   string
		public bool
	}{
		{"127.0.0.1", false},
		{"127.1.2.3", false},
		{"10.0.0.1", false},
		{"172.16.5.4", false},
		{"192.168.1.1", false},
		{"169.254.169.254", false},
		{"100.64.0.1", false},
		{"192.0.2.1", false},
		{"198.18.0.1", false},
		{"198.51.100.1", false},
		{"203.0.113.1", false},
		{"240.0.0.1", false},
		{"0.0.0.0", false},
		{"8.8.8.8", true},
		{"1.1.1.1", true},
		{"93.184.216.34", true},
		{"::1", false},
		{"fc00::1", false},
		{"fe80::1", false},
		{"ff02::1", false},
		{"2001:db8::1", false},
		{"2001::1", false},
		{"2002:7f00:1::", false},
		{"100::1", false},
		{"::7f00:1", false},
		{"64:ff9b::7f00:1", false},
		{"64:ff9b:1::1", false},
		{"::ffff:127.0.0.1", false},
		{"::ffff:8.8.8.8", true},
		{"2001:4860:4860::8888", true},
	}

	for _, test := range tests {
		addr, err := netip.ParseAddr(test.addr)
		if err != nil {
			t.Fatalf("parse %q: %v", test.addr, err)
		}

		if got := isPublicAddr(addr); got != test.public {
			t.Errorf("isPublicAddr(%s) = %v, want %v", test.addr, got, test.public)
		}
	}
}

func TestSafeDialerBlocksLoopback(t *testing.T) {
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(http.StatusNoContent)
	}))
	defer server.Close()

	client := func(allowPrivate bool) *http.Client {
		return &http.Client{
			Timeout: time.Second,
			Transport: &http.Transport{
				DialContext: newSafeDialer(time.Second, allowPrivate).DialContext,
			},
		}
	}

	response, err := client(false).Get(server.URL)
	if err == nil {
		response.Body.Close()
		t.Fatal("safe dialer allowed a loopback connection")
	}

	response, err = client(true).Get(server.URL)
	if err != nil {
		t.Fatalf("allow-private client failed: %v", err)
	}
	response.Body.Close()
}
