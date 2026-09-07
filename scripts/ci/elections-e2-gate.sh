#!/usr/bin/env bash
set -euo pipefail

DB_NAME="${ELECTIONS_E2_TEST_DB:-newearthcoop_elections_legacy}"
MYSQL_HOST="${DB_HOST:-127.0.0.1}"
MYSQL_PORT="${DB_PORT:-3306}"
MYSQL_USER="${DB_USERNAME:-root}"
MYSQL_PASSWORD="${DB_PASSWORD:-}"

MYSQL=(mysql --host="$MYSQL_HOST" --port="$MYSQL_PORT" --user="$MYSQL_USER")
if [[ -n "$MYSQL_PASSWORD" ]]; then
  MYSQL+=(--password="$MYSQL_PASSWORD")
fi

E2_MIGRATIONS=(
  "database/migrations/2026_08_21_145500_harden_election_vote_candidate_identity.php"
  "database/migrations/2026_08_21_150000_add_canonical_election_lifecycle_and_acceptance.php"
  "database/migrations/2026_08_21_151000_add_election_reconciliation_indexes.php"
)
E2_BOUNDARY_BASENAME="$(basename "${E2_MIGRATIONS[0]}")"

for migration in "${E2_MIGRATIONS[@]}"; do
  [[ -f "$migration" ]] || { echo "Missing E2 migration: $migration" >&2; exit 1; }
done

"${MYSQL[@]}" -e "DROP DATABASE IF EXISTS \`$DB_NAME\`; CREATE DATABASE \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

tmp_dir="$(mktemp -d)"
MOVED_MIGRATIONS=()
restore_migrations() {
  for migration in "${MOVED_MIGRATIONS[@]}"; do
    local_name="$(basename "$migration")"
    if [[ -f "$tmp_dir/$local_name" ]]; then
      mv "$tmp_dir/$local_name" "$migration"
    fi
  done
  rm -rf "$tmp_dir"
}
trap restore_migrations EXIT

# Build a true pre-E2 schema. E2 is the chronological boundary for this
# fixture, therefore every migration at or after the first E2 migration must
# be absent while the legacy baseline is created. This keeps the gate stable
# as E3/E4/later migrations are added and prevents later schemas from running
# against a database that intentionally has not received E2 yet.
while IFS= read -r migration; do
  migration_basename="$(basename "$migration")"
  if [[ "$migration_basename" < "$E2_BOUNDARY_BASENAME" ]]; then
    continue
  fi

  MOVED_MIGRATIONS+=("$migration")
  mv "$migration" "$tmp_dir/"
done < <(find database/migrations -maxdepth 1 -type f -name '*.php' | sort)

DB_DATABASE="$DB_NAME" php artisan migrate --force
restore_migrations
trap - EXIT

# Representative legacy snapshot:
# - user ids are intentionally far from Candidate ids;
# - vote #1 stores User.id directly in candidate_id (active runtime meaning);
# - vote #2 stores Candidate.id in candidate_id (legacy relation meaning);
# - acceptance values use the original textual ENUM contract.
"${MYSQL[@]}" "$DB_NAME" <<'SQL'
INSERT INTO users (id, email, password, first_name, last_name, status, created_at, updated_at) VALUES
  (101, 'e2-user-101@example.test', 'x', 'E2', 'CandidateOne', 'active', NOW(), NOW()),
  (102, 'e2-user-102@example.test', 'x', 'E2', 'CandidateTwo', 'active', NOW(), NOW()),
  (103, 'e2-voter-103@example.test', 'x', 'E2', 'Voter', 'active', NOW(), NOW());

INSERT INTO `groups` (id, name, group_type, created_at, updated_at)
VALUES (301, 'E2 Legacy Fixture Group', 'Alley', NOW(), NOW());

INSERT INTO elections (id, group_id, starts_at, ends_at, is_closed, created_at, updated_at)
VALUES (201, 301, '2026-08-01 00:00:00', '2026-09-01 00:00:00', 0, NOW(), NOW());

INSERT INTO candidates (id, election_id, user_id, position, accept_status, created_at, updated_at) VALUES
  (1, 201, 101, 'manager', 'accepted', NOW(), NOW()),
  (2, 201, 102, 'inspector', 'declined', NOW(), NOW());

INSERT INTO votes (id, election_id, voter_id, candidate_id, created_at, updated_at) VALUES
  (1, 201, 103, 102, NOW(), NOW()),
  (2, 201, 103, 1, NOW(), NOW());
SQL

# Apply only the E2 migrations in deterministic order.
for migration in "${E2_MIGRATIONS[@]}"; do
  DB_DATABASE="$DB_NAME" php artisan migrate --force --path="$migration"
done

DB_DATABASE="$DB_NAME" php artisan elections:audit-data --json --fail-on-issues

test "$("${MYSQL[@]}" "$DB_NAME" -N -B -e 'SELECT candidate_user_id FROM votes WHERE id = 1')" = "102"
test "$("${MYSQL[@]}" "$DB_NAME" -N -B -e 'SELECT candidate_user_id FROM votes WHERE id = 2')" = "101"
test "$("${MYSQL[@]}" "$DB_NAME" -N -B -e "SELECT lifecycle_status FROM elections WHERE id = 201")" = "open"
test "$("${MYSQL[@]}" "$DB_NAME" -N -B -e "SELECT acceptance_status FROM candidates WHERE id = 1")" = "accepted"
test "$("${MYSQL[@]}" "$DB_NAME" -N -B -e "SELECT acceptance_status FROM candidates WHERE id = 2")" = "declined"

# Roll back exactly the three E2 migrations. Each was applied in its own batch.
DB_DATABASE="$DB_NAME" php artisan migrate:rollback --force --step=3

test "$("${MYSQL[@]}" "$DB_NAME" -N -B -e 'SELECT COUNT(*) FROM votes')" = "2"
test "$("${MYSQL[@]}" "$DB_NAME" -N -B -e 'SELECT COUNT(*) FROM candidates')" = "2"
test "$("${MYSQL[@]}" "$DB_NAME" -N -B -e "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = '$DB_NAME' AND table_name = 'votes' AND column_name = 'candidate_user_id'")" = "0"
test "$("${MYSQL[@]}" "$DB_NAME" -N -B -e "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = '$DB_NAME' AND table_name = 'candidates' AND column_name = 'acceptance_status'")" = "0"
test "$("${MYSQL[@]}" "$DB_NAME" -N -B -e "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = '$DB_NAME' AND table_name = 'elections' AND column_name = 'lifecycle_status'")" = "0"

# Re-apply and audit again to prove rollback is recoverable and data-preserving.
for migration in "${E2_MIGRATIONS[@]}"; do
  DB_DATABASE="$DB_NAME" php artisan migrate --force --path="$migration"
done
DB_DATABASE="$DB_NAME" php artisan elections:audit-data --json --fail-on-issues

test "$("${MYSQL[@]}" "$DB_NAME" -N -B -e 'SELECT candidate_user_id FROM votes WHERE id = 1')" = "102"
test "$("${MYSQL[@]}" "$DB_NAME" -N -B -e 'SELECT candidate_user_id FROM votes WHERE id = 2')" = "101"

echo "E2 legacy upgrade + rollback gate passed."
