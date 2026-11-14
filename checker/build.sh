#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")"

version="${VERSION:-$(git describe --tags --always --dirty 2>/dev/null || echo dev)}"
out="${OUT:-dist}"

rm -rf "$out"
mkdir -p "$out"

build() {
	local arch="$1"
	local goarm="$2"
	local suffix="$3"
	local target="$out/2adm-checker_linux_${arch}${suffix}"

	echo "building $target ($version)"

	if [ -n "$goarm" ]; then
		CGO_ENABLED=0 GOOS=linux GOARCH="$arch" GOARM="$goarm" \
			go build -trimpath -ldflags="-s -w -X main.checkerVersion=$version" -o "$target" .
	else
		CGO_ENABLED=0 GOOS=linux GOARCH="$arch" \
			go build -trimpath -ldflags="-s -w -X main.checkerVersion=$version" -o "$target" .
	fi
}

build amd64 "" ""
build arm64 "" ""
build arm 7 "v7"

cp checker.env.example "$out/checker.env.example"
cp install.sh "$out/install.sh"
chmod +x "$out/install.sh"

for bin in "$out"/2adm-checker_linux_*; do
	case "$bin" in
	*.tar.gz) continue ;;
	esac

	arch_suffix="${bin##*/2adm-checker_linux_}"
	tar -C "$out" -czf "$out/2adm-checker_linux_${arch_suffix}.tar.gz" \
		"${bin##*/}" checker.env.example install.sh
done

(
	cd "$out"
	sha256sum 2adm-checker_linux_* > SHA256SUMS
)

echo "artifacts in $out"
ls -l "$out"
