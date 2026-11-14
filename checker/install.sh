#!/usr/bin/env bash
set -euo pipefail

APP="2adm-checker"
SERVICE_NAME="2adm-checker"
SERVICE_FILE="/etc/systemd/system/${SERVICE_NAME}.service"
DEFAULT_PREFIX="/opt/2adm-checker"
RUN_USER="2adm-checker"

BIN=""
CONFIG=""
PREFIX=""
DOWNLOAD_BASE=""
USE_SYSTEMD=1
UNINSTALL=0

log() { printf '%s\n' "$*"; }
die() { printf 'error: %s\n' "$*" >&2; exit 1; }

usage() {
	cat <<'EOF'
Usage: sudo ./install.sh [BIN] [CONFIG] [options]

Installs the checker as a static binary with checker.env next to it.
No Docker, no Go, no package manager required on the server.

Positional:
  BIN      path to the 2adm-checker binary (auto-detected by arch in script dir)
  CONFIG   path to the config file (defaults to checker.env.example)

Options:
  --download URL   download the binary and config from a release asset base URL
  --prefix DIR     install directory (default: /opt/2adm-checker)
  --no-systemd     only place the files, do not create a systemd service
  --uninstall      stop the service and remove installed files
  -h, --help       show this help

Examples:
  sudo ./install.sh
  sudo ./install.sh ./2adm-checker_linux_amd64 ./checker.env.example
  sudo ./install.sh --download https://github.com/org/repo/releases/latest/download
EOF
}

POSITIONAL=()

while [ $# -gt 0 ]; do
	case "$1" in
		--download) DOWNLOAD_BASE="${2:-}"; shift 2 || die "--download requires a URL" ;;
		--prefix) PREFIX="${2:-}"; shift 2 || die "--prefix requires a directory" ;;
		--no-systemd) USE_SYSTEMD=0; shift ;;
		--uninstall) UNINSTALL=1; shift ;;
		-h|--help) usage; exit 0 ;;
		--) shift; POSITIONAL+=("$@"); break ;;
		-*) die "unknown option: $1" ;;
		*) POSITIONAL+=("$1"); shift ;;
	esac
done

set -- "${POSITIONAL[@]}"

if [ $# -gt 0 ]; then
	BIN="$1"
	shift
fi

if [ $# -gt 0 ]; then
	CONFIG="$1"
	shift
fi

PREFIX="${PREFIX:-$DEFAULT_PREFIX}"
SOURCE="${BASH_SOURCE[0]:-$0}"
if [ -e "$SOURCE" ]; then
	SCRIPT_DIR="$(cd "$(dirname "$SOURCE")" && pwd)"
else
	SCRIPT_DIR="$PWD"
fi

detect_arch() {
	case "$(uname -m)" in
		x86_64|amd64) printf 'amd64' ;;
		aarch64|arm64) printf 'arm64' ;;
		armv7l|armv7|armv6l) printf 'armv7' ;;
		*) die "unsupported architecture: $(uname -m)" ;;
	esac
}

[ "$(id -u)" -eq 0 ] || die "run as root (sudo ./install.sh ...)"

if [ "$UNINSTALL" -eq 1 ]; then
	if command -v systemctl >/dev/null 2>&1; then
		systemctl stop "$SERVICE_NAME" 2>/dev/null || true
		systemctl disable "$SERVICE_NAME" 2>/dev/null || true
	fi
	rm -f "$SERVICE_FILE"
	if command -v systemctl >/dev/null 2>&1; then
		systemctl daemon-reload || true
	fi
	rm -rf "$PREFIX"
	if id "$RUN_USER" >/dev/null 2>&1; then
		userdel "$RUN_USER" 2>/dev/null || true
	fi
	log "removed $APP from $PREFIX"
	exit 0
fi

TMPDIR_CLEANUP=""
cleanup() {
	if [ -n "$TMPDIR_CLEANUP" ]; then
		rm -rf "$TMPDIR_CLEANUP"
	fi
}
trap cleanup EXIT

if [ -n "$DOWNLOAD_BASE" ]; then
	command -v curl >/dev/null 2>&1 || die "curl is required for --download"
	arch="$(detect_arch)"
	TMPDIR_CLEANUP="$(mktemp -d)"
	BIN="${BIN:-$TMPDIR_CLEANUP/2adm-checker_linux_$arch}"
	CONFIG="${CONFIG:-$TMPDIR_CLEANUP/checker.env.example}"
	log "downloading $DOWNLOAD_BASE/2adm-checker_linux_$arch"
	curl -fsSL "$DOWNLOAD_BASE/2adm-checker_linux_$arch" -o "$BIN" ||
		die "failed to download binary for $arch"
	curl -fsSL "$DOWNLOAD_BASE/checker.env.example" -o "$CONFIG" ||
		die "failed to download checker.env.example"
fi

if [ -z "$BIN" ]; then
	arch_bin="$SCRIPT_DIR/2adm-checker_linux_$(detect_arch)"
	if [ -f "$arch_bin" ]; then
		BIN="$arch_bin"
	elif [ -f "$SCRIPT_DIR/2adm-checker" ]; then
		BIN="$SCRIPT_DIR/2adm-checker"
	else
		BIN="$arch_bin"
	fi
fi
[ -f "$BIN" ] || die "binary not found: $BIN (pass BIN as first argument)"

if [ -z "$CONFIG" ]; then
	if [ -f "$SCRIPT_DIR/checker.env.example" ]; then
		CONFIG="$SCRIPT_DIR/checker.env.example"
	elif [ -f "$SCRIPT_DIR/checker.env" ]; then
		CONFIG="$SCRIPT_DIR/checker.env"
	fi
fi

ensure_user() {
	id "$RUN_USER" >/dev/null 2>&1 && return
	if command -v useradd >/dev/null 2>&1; then
		useradd --system --user-group --no-create-home --shell "$(command -v nologin || echo /bin/false)" "$RUN_USER"
	elif command -v adduser >/dev/null 2>&1; then
		adduser -S -D -H -s "$(command -v nologin || echo /bin/false)" "$RUN_USER"
	else
		die "cannot create system user $RUN_USER (no useradd/adduser)"
	fi
}

install -d -m 0755 "$PREFIX"
install -m 0755 "$BIN" "$PREFIX/$APP"
log "installed binary $PREFIX/$APP"

if [ -f "$PREFIX/checker.env" ]; then
	log "keeping existing config $PREFIX/checker.env"
else
	[ -n "$CONFIG" ] || die "config not found in $SCRIPT_DIR (pass CONFIG as second argument)"
	[ -f "$CONFIG" ] || die "config not found: $CONFIG"
	install -m 0600 "$CONFIG" "$PREFIX/checker.env"
	log "installed config $PREFIX/checker.env"
fi

if grep -q '^MONITOR_API_KEY=$' "$PREFIX/checker.env" 2>/dev/null; then
	log "WARNING: MONITOR_API_KEY is empty in $PREFIX/checker.env"
fi

current_guid="$(sed -n 's/^CHECKER_GUID=//p' "$PREFIX/checker.env" | head -n 1)"
if [ -z "${current_guid//[[:space:]]/}" ]; then
	generated_guid="$(cat /proc/sys/kernel/random/uuid 2>/dev/null || true)"
	if [ -n "$generated_guid" ]; then
		if grep -q '^CHECKER_GUID=' "$PREFIX/checker.env"; then
			sed -i "s|^CHECKER_GUID=.*|CHECKER_GUID=$generated_guid|" "$PREFIX/checker.env"
		else
			printf '\nCHECKER_GUID=%s\n' "$generated_guid" >> "$PREFIX/checker.env"
		fi
		log "generated checker GUID $generated_guid"
	else
		log "WARNING: cannot generate CHECKER_GUID; the binary will create checker.guid next to itself"
	fi
fi

if [ "$USE_SYSTEMD" -eq 1 ] && command -v systemctl >/dev/null 2>&1; then
	ensure_user
	chown -R "$RUN_USER": "$PREFIX"

	cat > "$SERVICE_FILE" <<EOF
[Unit]
Description=2adm site checker
After=network-online.target
Wants=network-online.target

[Service]
Type=simple
User=$RUN_USER
Group=$RUN_USER
WorkingDirectory=$PREFIX
ExecStart=$PREFIX/$APP
Restart=always
RestartSec=10
NoNewPrivileges=true
ProtectSystem=strict
ProtectHome=true
PrivateTmp=true
PrivateDevices=true
ProtectKernelTunables=true
ProtectKernelModules=true
ProtectControlGroups=true
RestrictSUIDSGID=true
LockPersonality=true

[Install]
WantedBy=multi-user.target
EOF

	systemctl daemon-reload
	systemctl enable "$SERVICE_NAME" >/dev/null 2>&1 || true
	systemctl restart "$SERVICE_NAME"
	log "service $SERVICE_NAME enabled and started"
	log "logs: journalctl -u $SERVICE_NAME -f"
else
	chmod 0600 "$PREFIX/checker.env" 2>/dev/null || true
	log "systemd skipped. start manually: $PREFIX/$APP"
fi

log "done"
