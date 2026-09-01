#!/usr/bin/env bash
set -euo pipefail

: "${DB_HOST:=127.0.0.1}"
: "${DB_PORT:=3306}"
: "${DB_USERNAME:=root}"
: "${DB_PASSWORD:=}"
: "${DB_DATABASE:?DB_DATABASE is required}"
: "${SECRETARIAT_STORAGE_DIR:=storage/app/secretariat}"
: "${SECRETARIAT_DR_OUTPUT:=storage/app/secretariat-dr}"
: "${KEEP_DRILL_DATABASE:=0}"

for binary in mysql mysqldump sha256sum tar; do
    command -v "$binary" >/dev/null 2>&1 || {
        echo "Missing required binary: $binary" >&2
        exit 2
    }
done

MYSQL=(mysql --protocol=TCP -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME")
MYSQLDUMP=(mysqldump --protocol=TCP -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME")
if [[ -n "$DB_PASSWORD" ]]; then
    export MYSQL_PWD="$DB_PASSWORD"
fi

mkdir -p "$SECRETARIAT_DR_OUTPUT"
stamp="$(date -u +%Y%m%dT%H%M%SZ)"
workdir="$SECRETARIAT_DR_OUTPUT/$stamp"
mkdir -p "$workdir"

drill_db="${DB_DATABASE}_secretariat_dr_${stamp//[^0-9A-Za-z]/_}"
cleanup() {
    if [[ "$KEEP_DRILL_DATABASE" != "1" ]]; then
        "${MYSQL[@]}" -e "DROP DATABASE IF EXISTS \`$drill_db\`;" >/dev/null 2>&1 || true
    fi
}
trap cleanup EXIT

mapfile -t tables < <("${MYSQL[@]}" --batch --skip-column-names "$DB_DATABASE" -e "SHOW TABLES LIKE 'secretariat\\_%';")
if [[ ${#tables[@]} -eq 0 ]]; then
    echo "No Secretariat tables found in $DB_DATABASE" >&2
    exit 3
fi

printf '%s\n' "${tables[@]}" > "$workdir/secretariat-tables.txt"

# A Secretariat-only SQL dump is not independently restorable because Registry
# rows intentionally reference users, groups and other source-domain rows. The
# disaster-recovery artifact therefore captures the complete transactional DB,
# while verification below remains focused on the bounded Secretariat tables.
"${MYSQLDUMP[@]}" \
    --single-transaction \
    --skip-lock-tables \
    --set-gtid-purged=OFF \
    --no-tablespaces \
    "$DB_DATABASE" > "$workdir/database.sql"
sha256sum "$workdir/database.sql" > "$workdir/database.sql.sha256"

if [[ -d "$SECRETARIAT_STORAGE_DIR" ]]; then
    tar -C "$(dirname "$SECRETARIAT_STORAGE_DIR")" -czf "$workdir/secretariat-storage.tar.gz" "$(basename "$SECRETARIAT_STORAGE_DIR")"
else
    tar -czf "$workdir/secretariat-storage.tar.gz" --files-from /dev/null
fi
sha256sum "$workdir/secretariat-storage.tar.gz" > "$workdir/secretariat-storage.tar.gz.sha256"

"${MYSQL[@]}" -e "DROP DATABASE IF EXISTS \`$drill_db\`; CREATE DATABASE \`$drill_db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
"${MYSQL[@]}" "$drill_db" < "$workdir/database.sql"

mismatch=0
{
    echo "table,source_count,restored_count,status"
    for table in "${tables[@]}"; do
        source_count="$("${MYSQL[@]}" --batch --skip-column-names "$DB_DATABASE" -e "SELECT COUNT(*) FROM \`$table\`;")"
        restored_count="$("${MYSQL[@]}" --batch --skip-column-names "$drill_db" -e "SELECT COUNT(*) FROM \`$table\`;")"
        status="ok"
        if [[ "$source_count" != "$restored_count" ]]; then
            status="mismatch"
            mismatch=1
        fi
        echo "$table,$source_count,$restored_count,$status"
    done
} > "$workdir/secretariat-row-count-verification.csv"

cat > "$workdir/manifest.txt" <<EOF
schema=earthcoop.secretariat.dr.v1
created_at_utc=$stamp
backup_scope=full_database_plus_secretariat_storage
source_database=$DB_DATABASE
drill_database=$drill_db
secretariat_table_count=${#tables[@]}
storage_source=$SECRETARIAT_STORAGE_DIR
database_sha256=$(cut -d' ' -f1 "$workdir/database.sql.sha256")
storage_sha256=$(cut -d' ' -f1 "$workdir/secretariat-storage.tar.gz.sha256")
EOF

if [[ "$mismatch" != "0" ]]; then
    echo "Secretariat DR drill FAILED: restored Secretariat row counts differ. Evidence: $workdir" >&2
    exit 4
fi

echo "Secretariat DR drill PASS"
echo "Evidence: $workdir"
