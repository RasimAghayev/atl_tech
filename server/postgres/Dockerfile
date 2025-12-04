# Use the official PostgreSQL Alpine image
FROM postgres:18.1-alpine

# Add metadata labels
LABEL maintainer="Rasim Aghayev <rasimaqayev@gmail.com>" \
      version="1.0" \
      description="Production-ready Postgres server"

# Set environment variables for customization
ENV POSTGRES_DB=myapp \
    POSTGRES_USER=myapp \
    POSTGRES_PASSWORD=mypassword \
    PGDATA=/var/lib/postgresql/data/pgdata

# Install additional tools and extensions in a single RUN command to reduce layers
RUN apk add --no-cache \
    pg_cron \
    postgis \
    timescaledb \
    && mkdir -p /docker-entrypoint-initdb.d \
    #&& apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Copy initialization scripts and custom configuration
#COPY ./server/postgres /docker-entrypoint-initdb.d/
COPY ./server/postgres/postgresql.conf /etc/postgresql/postgresql.conf

# Switch to root to create PGDATA directory with appropriate permissions
USER root

# Create necessary directories and set correct permissions
RUN mkdir -p "$PGDATA" \
    && chown -R postgres:postgres "$PGDATA" \
    && chmod 700 "$PGDATA"

# Use default postgres user to run PostgreSQL
USER postgres

# Health check to ensure PostgreSQL is ready
HEALTHCHECK --interval=30s --timeout=5s --retries=3 \
    CMD pg_isready -U $POSTGRES_USER -d $POSTGRES_DB || exit 1

# Expose PostgreSQL port
EXPOSE 5432

# Set the default command with custom configuration
CMD ["postgres", "-c", "config_file=/etc/postgresql/postgresql.conf"]
