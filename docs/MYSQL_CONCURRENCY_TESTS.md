# Isolated MySQL concurrency verification

Run `bash tests/run-weekly-mysql-concurrency.sh` and
`bash tests/run-gifts-mysql-concurrency.sh` only from a disposable **local or CI
checkout** with development Composer dependencies installed and no `.env` or
`.env.testing`. Do not copy application dotenv files, media storage, diagnostic
scripts, credentials, or production database dumps into that checkout. A plain
CI checkout plus `composer install` is sufficient.

Each runner refuses inherited application/database/provider settings and
nonlocal Docker contexts, builds the app image without dotenv input, creates a
unique Docker network and RAM-backed MySQL container, and verifies the actual
database name, host and server UUID **before** running migrations. The tests use
only this disposable database. The trap removes the container and network at
exit. Review the runner and Docker context before using it in a new CI system.

The weekly runner covers two tests; the gifts runner covers seven. SQLite
feature tests do not replace these MySQL row-lock checks. The main working
checkout deliberately refuses the runners when its `.env` exists.
