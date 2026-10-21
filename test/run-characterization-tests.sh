#!/usr/bin/env bash
set -euo pipefail

projectDirectory=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
postgresBin=${PHP85_TEST_POSTGRES_BIN:-}
if [[ -z "$postgresBin" ]] && command -v pg_config >/dev/null 2>&1; then
    postgresBin=$(pg_config --bindir)
fi
if [[ ! -x "$postgresBin/initdb" || ! -x "$postgresBin/pg_ctl" ]]; then
    echo 'Set PHP85_TEST_POSTGRES_BIN to a directory containing initdb and pg_ctl.' >&2
    exit 1
fi

testDirectory=$(mktemp -d /tmp/backendbase-postgres.XXXXXX)
cleanup() {
    if [[ -f "$testDirectory/data/postmaster.pid" ]]; then
        "$postgresBin/pg_ctl" -D "$testDirectory/data" -m immediate -w stop
    fi
    rm -rf -- "$testDirectory"
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

touch "$testDirectory/backendbase-test-cluster"
if ! "$postgresBin/initdb" -D "$testDirectory/data" --username=php85_test \
    --auth-local=trust --auth-host=reject --no-locale --encoding=UTF8 \
    > "$testDirectory/init.log" 2>&1; then
    cat "$testDirectory/init.log" >&2
    exit 1
fi
if ! "$postgresBin/pg_ctl" -D "$testDirectory/data" -l "$testDirectory/server.log" \
    -o "-k $testDirectory -p 55485 -c listen_addresses= -c unix_socket_permissions=0700" -w start; then
    cat "$testDirectory/server.log" >&2
    exit 1
fi

cd "$projectDirectory"
export PHP85_TEST_POSTGRES_SOCKET="$testDirectory"
"${PHP85_TEST_PHP_BINARY:-php}" -d error_reporting=-1 -d zend.assertions=1 vendor/bin/phpunit "$@"
