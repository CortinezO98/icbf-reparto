# ICBF Reparto

Base técnica del Módulo de Reparto de Peticiones ICBF.

## Stack

- PHP 8.3 FPM
- Nginx
- MariaDB 10.11
- Docker / Docker Compose
- Composer
- PHPUnit
- GitHub Actions

## Requisitos locales

- Docker Desktop
- Git
- VS Code (recomendado)

No se requiere XAMPP ni instalar PHP/MariaDB en Windows.

## Primer arranque

1. Copie el archivo de variables:

```powershell
Copy-Item .env.example .env
```

2. Edite `.env` y defina valores locales fuertes para las variables obligatorias.

3. Construya y levante los contenedores:

```powershell
docker compose up -d --build
```

4. Instale dependencias si el contenedor aún no las instaló:

```powershell
docker compose exec app composer install
```

5. Ejecute las migraciones:

```powershell
docker compose exec app php bin/migrate.php
```

6. Cree el primer administrador:

```powershell
docker compose exec app php bin/create-admin.php
```

7. Abra:

```text
http://localhost:8088
```

## Comandos útiles

```powershell
docker compose ps
docker compose logs -f nginx
docker compose logs -f app
docker compose logs -f db
docker compose exec app php bin/migrate.php
docker compose exec app composer test
docker compose exec app composer analyse
docker compose logs -f worker
docker compose down
```

Para detener sin eliminar la base:

```powershell
docker compose down
```

Para eliminar también los volúmenes de desarrollo:

```powershell
docker compose down -v
```

## Flujo Git recomendado

```bash
git init
git add .
git commit -m "chore: initial project foundation"
git branch -M main
git checkout -b develop
```

Después de crear el repositorio vacío en GitHub:

```bash
git remote add origin <URL_REPOSITORIO>
git push -u origin main
git push -u origin develop
```

Las funcionalidades se desarrollan en ramas `feature/*` creadas desde `develop`.

## Seguridad

- `.env` está excluido de Git.
- No se almacenan contraseñas en texto claro.
- Se usa Argon2id cuando está disponible.
- CSRF en formularios POST.
- Sesiones con `HttpOnly` y `SameSite=Lax`.
- Consultas parametrizadas con PDO.
- Nginx solo publica `public/`.
- MariaDB no expone puerto al host por defecto.
- Los errores detallados no se muestran cuando `APP_DEBUG=0`.


## Fase 2 - actualización

Después de reemplazar archivos:

```cmd
docker compose build --no-cache app
docker compose up -d
docker compose exec app php bin/migrate.php
```

Nuevas rutas:

- `/admin/structures`
- `/admin/queues`

## Automatización operativa

El servicio `worker` ejecuta de forma continua el reparto de casos, el procesamiento de fin de turno y la evaluación de ANS.

Después de actualizar el código:

```powershell
docker compose up -d --build
docker compose exec app php bin/migrate.php
docker compose ps
docker compose logs -f worker
```

La administración de turnos está disponible en:

```text
http://localhost:8088/admin/shifts
```

Variables configurables:

```text
WORKER_INTERVAL_SECONDS=10
SLA_WORKER_INTERVAL_SECONDS=300
```

## Reasignación de casos

Los perfiles con permiso `CASE_REASSIGN` pueden reasignar un caso abierto desde su detalle.

La operación exige un motivo y valida nuevamente el agente destino antes de confirmar:

- agente activo y habilitado para reparto;
- presencia y heartbeat vigentes;
- turno vigente;
- cola y skills;
- capacidad disponible.

La asignación anterior se cierra con `MANUAL_REASSIGN`, la nueva se registra como `REASSIGN` y el usuario que ejecutó la operación queda en `assigned_by`. El evento `CASE_REASSIGNED` conserva origen, destino y motivo.
