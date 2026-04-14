#!/usr/bin/env bash
# Filament v4 smoke test using curl against the live dev server.
#
# Authenticates via the actual login form per panel, then iterates all
# panel routes. A route "passes" if it returns 200/302 with no server-side
# error banner in the body.
#
# Usage:  bash tests/smoke-filament-v4.sh [base_url]
#         default base_url = https://abi-zaid-quran.test

set -u
BASE_URL="${1:-https://abi-zaid-quran.test}"
CURL_OPTS=(-sS -k -L --max-time 20)

FAILED=()
PASSED=0
TOTAL=0

# Extract a hidden input value from an HTML page.
extract_input() {
    local html="$1" name="$2"
    echo "$html" | grep -Eo "name=\"${name}\"[^>]*value=\"[^\"]*\"|value=\"[^\"]*\"[^>]*name=\"${name}\"" \
        | head -1 \
        | grep -Eo 'value="[^"]*"' \
        | head -1 \
        | sed -E 's/value="([^"]*)"/\1/'
}

# Extract a Livewire snapshot/update metadata value.
extract_wire() {
    local html="$1" attr="$2"
    echo "$html" | grep -Eo "${attr}=\"[^\"]*\"" | head -1 | sed -E "s/${attr}=\"([^\"]*)\"/\1/"
}

# Log in via Filament's login Livewire component by POSTing the
# traditional login form (Filament also registers a form submit URL).
filament_login() {
    local cookies="$1" login_url="$2" email="$3" password="$4"
    local page
    page=$(curl "${CURL_OPTS[@]}" -c "$cookies" -b "$cookies" "$login_url")
    # Grab CSRF token
    local token
    token=$(echo "$page" | grep -Eo 'name="_token"[^>]*value="[^"]*"' | head -1 | grep -Eo 'value="[^"]*"' | sed 's/value="//;s/"//')
    if [[ -z "$token" ]]; then
        # Try meta tag csrf-token
        token=$(echo "$page" | grep -Eo 'name="csrf-token" content="[^"]*"' | head -1 | grep -Eo 'content="[^"]*"' | sed 's/content="//;s/"//')
    fi

    # Use Livewire's /livewire/update endpoint to authenticate.
    # First grab the login component's snapshot/id from the page.
    local snapshot livewire_id
    snapshot=$(echo "$page" | grep -Eo 'wire:snapshot="[^"]+"' | head -1 | sed -E 's/wire:snapshot="([^"]+)"/\1/' | sed 's/&quot;/"/g')
    livewire_id=$(echo "$page" | grep -Eo 'wire:id="[^"]+"' | head -1 | sed -E 's/wire:id="([^"]+)"/\1/')

    if [[ -z "$snapshot" || -z "$livewire_id" ]]; then
        echo "LOGIN FAIL: could not find Livewire snapshot on $login_url" >&2
        return 1
    fi

    # Build Livewire update payload.
    # Decode snapshot (it was HTML entity encoded).
    local decoded_snapshot="$snapshot"

    local payload
    payload=$(php -r '
        $snapshot = $argv[1];
        $updates = [
            "data.email" => $argv[2],
            "data.password" => $argv[3],
        ];
        echo json_encode([
            "_token" => $argv[4],
            "components" => [
                [
                    "snapshot" => $snapshot,
                    "updates" => (object) $updates,
                    "calls" => [
                        [
                            "path" => "",
                            "method" => "authenticate",
                            "params" => [],
                        ],
                    ],
                ],
            ],
        ]);
    ' -- "$decoded_snapshot" "$email" "$password" "$token")

    local base="${login_url%/*}"
    local origin="${BASE_URL%/}"
    local update_resp
    update_resp=$(curl "${CURL_OPTS[@]}" -c "$cookies" -b "$cookies" \
        -X POST "${origin}/livewire/update" \
        -H "Content-Type: application/json" \
        -H "X-CSRF-TOKEN: $token" \
        -H "X-Livewire: true" \
        -H "Referer: $login_url" \
        --data "$payload")

    # If authenticate succeeded, Livewire response will contain a redirect.
    if echo "$update_resp" | grep -q '"redirect"'; then
        return 0
    fi
    echo "LOGIN FAIL response (first 400 chars): $(echo "$update_resp" | head -c 400)" >&2
    return 2
}

test_route() {
    local cookies="$1" path="$2" panel="$3"
    TOTAL=$((TOTAL+1))
    local url="${BASE_URL}${path}"
    local out
    out=$(curl "${CURL_OPTS[@]}" -b "$cookies" -c "$cookies" -o /tmp/smoke_body.$$ -w "%{http_code}" "$url")
    local body
    body=$(cat /tmp/smoke_body.$$ 2>/dev/null || echo "")
    rm -f /tmp/smoke_body.$$

    local status="$out"
    local title
    title=$(echo "$body" | grep -Eo '<title[^>]*>[^<]+</title>' | head -1 | sed -E 's/<[^>]+>//g' | sed 's/^[ \t]*//')
    local body_len=${#body}

    # Detect error indicators.
    local error=""
    if [[ "$status" != "200" && "$status" != "302" && "$status" != "301" ]]; then
        error="HTTP $status"
    elif echo "$body" | grep -q 'Ignition\|Whoops\|class=&quot;exception'; then
        error="exception page"
    elif echo "$body" | grep -qE 'Class &quot;[^&]+&quot; not found|Call to undefined method|Property \[\$[a-zA-Z_]+\] not found'; then
        error=$(echo "$body" | grep -Eo 'Class &quot;[^&]+&quot; not found|Call to undefined method [^<"]+|Property \[\$[a-zA-Z_]+\] not found on component: [^<"]+' | head -1 | head -c 200)
    fi

    if [[ -n "$error" ]]; then
        FAILED+=("[$panel] $path :: $error :: title=$title")
        echo "  ✗ $status $path  [$error]"
    else
        PASSED=$((PASSED+1))
        echo "  ✓ $status $path"
    fi
}

run_panel() {
    local panel="$1" email="$2" password="$3" shift_routes_from="$4"
    shift 4
    local routes=("$@")
    local cookies
    cookies=$(mktemp -t "smoke-${panel}-XXXXXX")
    local login_url="${BASE_URL}/${panel}/login"
    [[ "$panel" == "quran-program" ]] && login_url="${BASE_URL}/quran-program/login"

    echo ""
    echo "=== Panel: $panel (as $email) ==="
    if ! filament_login "$cookies" "$login_url" "$email" "$password"; then
        echo "  ✗ LOGIN FAILED for panel $panel (skipping routes)"
        FAILED+=("[$panel] LOGIN FAILED")
        rm -f "$cookies"
        return
    fi
    for path in "${routes[@]}"; do
        test_route "$cookies" "$path" "$panel"
    done
    rm -f "$cookies"
}

# Fetch representative IDs from the DB so we can test record view/edit routes.
ids_json=$(php artisan tinker --execute="
echo json_encode([
    'group' => App\Models\Group::value('id'),
    'student' => App\Models\Student::value('id'),
    'teacher' => App\Models\User::where('role','teacher')->value('id'),
    'user' => App\Models\User::where('role','!=','teacher')->value('id'),
    'progress' => App\Models\Progress::value('id'),
]);
" 2>/dev/null | tail -1)

GROUP_ID=$(echo "$ids_json" | php -r 'echo json_decode(file_get_contents("php://stdin"), true)["group"] ?? "";')
STUDENT_ID=$(echo "$ids_json" | php -r 'echo json_decode(file_get_contents("php://stdin"), true)["student"] ?? "";')
TEACHER_ID=$(echo "$ids_json" | php -r 'echo json_decode(file_get_contents("php://stdin"), true)["teacher"] ?? "";')
USER_ID=$(echo "$ids_json" | php -r 'echo json_decode(file_get_contents("php://stdin"), true)["user"] ?? "";')
PROGRESS_ID=$(echo "$ids_json" | php -r 'echo json_decode(file_get_contents("php://stdin"), true)["progress"] ?? "";')

ASSOC_ROUTES=(
    /association
    /association/groups
    /association/guardians
    /association/guardians/create
    /association/memorizers
    /association/memorizers/create
    /association/payments
    /association/payments/create
    /association/teachers
    /association/teachers/create
    /association/scan-attendance
    /association/whats-app-sessions
    /association/whats-app-message-histories
)
[[ -n "$GROUP_ID" ]] && ASSOC_ROUTES+=(/association/groups/$GROUP_ID)
[[ -n "$TEACHER_ID" ]] && ASSOC_ROUTES+=(/association/teachers/$TEACHER_ID /association/teachers/$TEACHER_ID/edit)

QURAN_ROUTES=(
    /quran-program
    /quran-program/groups
    /quran-program/groups/create
    /quran-program/messages
    /quran-program/messages/create
    /quran-program/pages
    /quran-program/pages/create
    /quran-program/progress
    /quran-program/progress/create
    /quran-program/reminder-report
    /quran-program/scan-attendance
    /quran-program/scan-qr-code
    /quran-program/student-disconnections
    /quran-program/student-disconnections/create
    /quran-program/students
    /quran-program/students/create
    /quran-program/subtitle-cleaner
    /quran-program/users
    /quran-program/users/create
    /quran-program/whats-app-sessions
)
[[ -n "$GROUP_ID" ]] && QURAN_ROUTES+=(/quran-program/groups/$GROUP_ID /quran-program/groups/$GROUP_ID/edit)
[[ -n "$STUDENT_ID" ]] && QURAN_ROUTES+=(/quran-program/students/$STUDENT_ID /quran-program/students/$STUDENT_ID/edit)
[[ -n "$PROGRESS_ID" ]] && QURAN_ROUTES+=(/quran-program/progress/$PROGRESS_ID/edit)
[[ -n "$USER_ID" ]] && QURAN_ROUTES+=(/quran-program/users/$USER_ID/edit)

TEACHER_ROUTES=(
    /teacher
    /teacher/groups
)

run_panel association admin@association.com password 0 "${ASSOC_ROUTES[@]}"
run_panel quran-program admin@admin.com password 0 "${QURAN_ROUTES[@]}"
run_panel teacher teacher1@test.com password 0 "${TEACHER_ROUTES[@]}"

echo ""
echo "=== Summary ==="
echo "Total: $TOTAL  Passed: $PASSED  Failed: ${#FAILED[@]}"
if [[ ${#FAILED[@]} -gt 0 ]]; then
    echo ""
    echo "Failures:"
    for f in "${FAILED[@]}"; do
        echo "  - $f"
    done
    exit 1
fi
exit 0
