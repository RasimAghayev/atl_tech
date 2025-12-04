#!/bin/bash
set -e

# Export password for PostgreSQL commands
export PGPASSWORD=${POSTGRES_PASSWORD}

check_db_ready() {
    echo "Checking if the database is ready for restore..."
    until pg_isready -h localhost -U ${POSTGRES_USER}; do
        echo "Waiting for PostgreSQL to be ready..."
        sleep 2
    done
}

db_exists() {
    psql -U ${POSTGRES_USER} -d ${POSTGRES_DB} -c '\q' 2>/dev/null
    return $?
}

create_db() {
    echo "Database not found, creating the database..."
    psql -U ${POSTGRES_USER} -d postgres -c "CREATE DATABASE ${POSTGRES_DB} \
        WITH OWNER ${POSTGRES_USER} \
        ENCODING 'UTF8' \
        LC_COLLATE='ru_RU.UTF-8' \
        LC_CTYPE='ru_RU.UTF-8' \
        TEMPLATE=template0;"
}

restore_backup() {
    echo "Unzipping and restoring data from backup..."
    unzip -p /docker-entrypoint-initdb.d/backup.sql.zip > /docker-entrypoint-initdb.d/backup.sql
    psql -U ${POSTGRES_USER} -d ${POSTGRES_DB} -f /docker-entrypoint-initdb.d/backup.sql
    rm -f /docker-entrypoint-initdb.d/backup.sql
}

main() {
    echo "Starting database restore process..."
    check_db_ready

    if db_exists; then
        echo "Database ${POSTGRES_DB} exists"
        restore_backup
    else
        echo "Database ${POSTGRES_DB} does not exist"
        create_db
        restore_backup
    fi

    echo "Database restore completed successfully!"
}

main