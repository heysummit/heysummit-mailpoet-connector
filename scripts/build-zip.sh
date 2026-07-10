#!/usr/bin/env bash
# Build the production WordPress plugin archive without development tooling.

set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
dist_root="${repo_root}/dist"
package_root="${dist_root}/heysummit-mailpoet-connector"
archive_path="${dist_root}/heysummit-mailpoet-connector.zip"

rm -rf "${dist_root}"
mkdir -p "${package_root}/includes"

cp "${repo_root}/heysummit-mailpoet-connector.php" "${package_root}/"
cp "${repo_root}/readme.txt" "${package_root}/"
cp "${repo_root}/LICENSE" "${package_root}/"
cp "${repo_root}"/includes/*.php "${package_root}/includes/"

(
	cd "${dist_root}"
	zip -qr "${archive_path}" heysummit-mailpoet-connector
)

printf 'Built %s\n' "${archive_path}"
