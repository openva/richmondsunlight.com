#!/usr/bin/env bash
#==================================================================================
# Run the SonarQube Cloud analysis from here, before pushing. It analyzes the
# working tree (uncommitted changes included) as a branch named "local-" plus the
# current branch's name, which SonarQube Cloud compares with master and deletes
# after 30 days without an analysis. The results are at the URL printed at the end.
#
# For test coverage, run the PHPUnit tests in Docker first, with ./docker-run.sh
# and then ./docker-tests.sh (or just: docker compose exec web
# /var/www/deploy/tests/run-all.sh). Without that, coverage reads as 0%.
#
# Needs a SonarQube Cloud token, in $SONAR_TOKEN or in ~/.sonar-token. Generate one
# at https://sonarcloud.io/account/security, which the "My account" page doesn't
# link to. Any other arguments are passed to the scanner, e.g. -Dsonar.verbose=true.
#==================================================================================
set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."
repo="$(pwd)"

SCANNER_IMAGE="sonarsource/sonar-scanner-cli:12.2.0.4256_8.1.0"

if [[ -z "${SONAR_TOKEN:-}" ]]; then
    token_file="${SONAR_TOKEN_FILE:-$HOME/.sonar-token}"
    if [[ ! -f "$token_file" ]]; then
        echo "Put a SonarQube Cloud token in \$SONAR_TOKEN or $token_file." >&2
        exit 1
    fi
    SONAR_TOKEN="$(< "$token_file")"
fi
export SONAR_TOKEN

# The coverage report names files by their paths in the web container
if [[ -f coverage/clover.xml ]]; then
    sed -i.bak "s#/var/www/#$repo/#g" coverage/clover.xml && rm coverage/clover.xml.bak
else
    echo "No coverage report (coverage/clover.xml), so coverage will read as 0%." >&2
fi

branch="$(git rev-parse --abbrev-ref HEAD)"

# The repository is mounted at the same path as here, so that the coverage report's paths work
docker run --rm \
    --user "$(id -u):$(id -g)" \
    -e SONAR_TOKEN \
    -e SONAR_USER_HOME="$repo/.scannerwork/home" \
    -v "$repo:$repo" \
    -w "$repo" \
    "$SCANNER_IMAGE" \
    -Dsonar.projectBaseDir="$repo" \
    -Dsonar.branch.name="local-$branch" \
    "$@"
