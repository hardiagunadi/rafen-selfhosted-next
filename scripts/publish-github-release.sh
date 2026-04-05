#!/usr/bin/env bash
set -Eeuo pipefail

if [ "$#" -lt 4 ]; then
    cat >&2 <<'EOF'
Usage:
  GITHUB_TOKEN=... ./scripts/publish-github-release.sh <owner> <repo> <tag> <manifest_path> [release_notes_path]

Example:
  GITHUB_TOKEN=ghp_xxx ./scripts/publish-github-release.sh \
    hardiagunadi \
    rafen-selfhosted-next \
    v2026.04.05-main.1 \
    /tmp/release-manifest-v2026.04.05-main.1.json
EOF
    exit 1
fi

OWNER="$1"
REPO="$2"
TAG="$3"
MANIFEST_PATH="$4"
RELEASE_NOTES_PATH="${5:-}"
API_BASE="https://api.github.com/repos/${OWNER}/${REPO}"
API_VERSION="2022-11-28"
AUTH_HEADER="Authorization: Bearer ${GITHUB_TOKEN:-}"

if [ -z "${GITHUB_TOKEN:-}" ]; then
    echo "GITHUB_TOKEN wajib diisi." >&2
    exit 1
fi

if [ ! -f "$MANIFEST_PATH" ]; then
    echo "Manifest tidak ditemukan: $MANIFEST_PATH" >&2
    exit 1
fi

if [ -n "$RELEASE_NOTES_PATH" ] && [ ! -f "$RELEASE_NOTES_PATH" ]; then
    echo "Release notes tidak ditemukan: $RELEASE_NOTES_PATH" >&2
    exit 1
fi

json_escape() {
    php -r '$v = stream_get_contents(STDIN); echo json_encode($v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);'
}

build_release_payload() {
    local body_json
    local name_json
    local tag_json

    tag_json="$(printf '%s' "$TAG" | json_escape)"
    name_json="$(printf '%s' "$TAG" | json_escape)"

    if [ -n "$RELEASE_NOTES_PATH" ]; then
        body_json="$(cat "$RELEASE_NOTES_PATH" | json_escape)"
    else
        body_json='""'
    fi

    cat <<EOF
{
  "tag_name": ${tag_json},
  "name": ${name_json},
  "body": ${body_json},
  "draft": false,
  "prerelease": false,
  "generate_release_notes": false
}
EOF
}

request_release() {
    local method="$1"
    local url="$2"
    local data="${3:-}"

    if [ -n "$data" ]; then
        curl -fsS -X "$method" \
            -H "$AUTH_HEADER" \
            -H "Accept: application/vnd.github+json" \
            -H "X-GitHub-Api-Version: ${API_VERSION}" \
            -H "Content-Type: application/json" \
            "$url" \
            --data "$data"
        return
    fi

    curl -fsS -X "$method" \
        -H "$AUTH_HEADER" \
        -H "Accept: application/vnd.github+json" \
        -H "X-GitHub-Api-Version: ${API_VERSION}" \
        "$url"
}

extract_field() {
    local field="$1"

    php -r '
        $json = stream_get_contents(STDIN);
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $field = $argv[1];
        $value = $data[$field] ?? null;

        if (!is_string($value) || $value === "") {
            fwrite(STDERR, "Field tidak ditemukan: {$field}\n");
            exit(1);
        }

        echo $value;
    ' "$field"
}

echo "Memeriksa release ${TAG}..."

release_response=""

if release_response="$(request_release GET "${API_BASE}/releases/tags/${TAG}" 2>/dev/null)"; then
    echo "Release sudah ada, akan dipakai ulang."
else
    echo "Membuat release baru ${TAG}..."
    release_response="$(request_release POST "${API_BASE}/releases" "$(build_release_payload)")"
fi

upload_url="$(printf '%s' "$release_response" | extract_field upload_url)"
upload_url="${upload_url%\{*}"

echo "Mengunggah asset release-manifest.json..."

curl -fsS -X POST \
    -H "$AUTH_HEADER" \
    -H "Accept: application/vnd.github+json" \
    -H "X-GitHub-Api-Version: ${API_VERSION}" \
    -H "Content-Type: application/json" \
    --data-binary @"$MANIFEST_PATH" \
    "${upload_url}?name=release-manifest.json"

echo
echo "Selesai."
