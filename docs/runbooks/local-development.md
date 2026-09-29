# Desarrollo local

## Objetivo

Levantar el entorno sin XAMPP y con componentes equivalentes a producción.

## Inicio

```powershell
Copy-Item .env.example .env
docker compose up -d --build
docker compose exec app php bin/migrate.php
docker compose exec app php bin/create-admin.php
```

Portal:

```text
http://localhost:8088
```

Health check:

```text
http://localhost:8088/health
```

## Ver estado

```powershell
docker compose ps
docker compose logs -f nginx
docker compose logs -f app
docker compose logs -f db
```

## Detener

```powershell
docker compose down
```

No usar `-v` si se desea conservar la base local.

## Reinicio limpio de desarrollo

Solo cuando se quiera borrar deliberadamente la base local:

```powershell
docker compose down -v
docker compose up -d --build
docker compose exec app php bin/migrate.php
```
