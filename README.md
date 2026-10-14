# Sistema de Inventario de Laboratorio — Arquitectura Multicontenedor

Aplicación web distribuida desarrollada con Docker Compose para la gestión y control de equipos de laboratorio escolar, implementando persistencia de datos relacionales, almacenamiento en caché temporal, notificaciones por correo simulado y proxy inverso.

---

## 📋 Información General

* **Asignatura:** Infraestructura de Servicios
* **Estudiante:** José Antonio Navarro Meza
* **Institución:** Instituto Tecnológico de Morelia
* **Sistema Asignado:** Inventario de laboratorio
* **Entidad Principal:** Equipo
* **Mejora Individual Implementada:** Filtro de Búsqueda Dinámico (Frontend, Backend y Redis)

---

## 🏗️ Arquitectura de Servicios

El sistema se compone de 5 microservicios desacoplados que operan bajo una red interna tipo puente (`app_network`):

| Servicio | Tecnología | Rol / Responsabilidad | Exposición |
| :--- | :--- | :--- | :--- |
| **frontend** | Nginx (Alpine) | Sirve archivos estáticos (HTML/CSS/JS) y actúa como proxy inverso de `/api/` hacia el backend. | Puerto Host `8081` |
| **backend** | PHP 8.3 + Apache | API RESTful, lógica de negocio, validaciones, conexión a DB, Redis y SMTP. | Solo red interna (`backend:80`) |
| **db** | MariaDB 11.4 | Persistencia relacional de los registros de la tabla `equipos`. | Solo red interna (`db:3306`) |
| **redis** | Redis 7 (Alpine) | Caché de consultas (TTL 30s) y contador acumulado de visitas/consultas. | Solo red interna (`redis:6379`) |
| **mailpit** | Mailpit | Servidor SMTP de desarrollo y bandeja web para capturar correos simulados al crear registros. | Puerto Host `8025` (Web UI) |

### Flujo de Comunicación y Aislamiento
* **Acceso Público:** Únicamente los puertos `8081` (Frontend) y `8025` (Mailpit Web) están publicados al host.
* **Red Aislada (`app_network`):** `db`, `redis` y `backend` no tienen puertos publicados hacia la máquina host ni hacia Internet, comunicándose exclusivamente por nombres de servicio resueltos por el DNS interno de Docker Engine (`127.0.0.11`).

---

## 🚀 Requisitos Previos

* Sistema Operativo: Windows, Linux o macOS
* Docker Engine / Docker Desktop con Docker Compose v2 instalado
* Cliente Git
* Navegador web moderno o `curl`

---

## ⚙️ Estructura del Proyecto

```text
proyecto-navarromeza-joseantonio/
├── compose.yaml
├── .env
├── .env.example
├── .gitignore
├── README.md
├── database/
│   └── init.sql
├── backend/
│   ├── Dockerfile
│   └── src/
│       ├── index.php
│       └── health.php
└── frontend/
    ├── Dockerfile
    ├── nginx.conf
    └── src/
        ├── index.html
        ├── styles.css
        └── app.js

🛠️ Instalación y Puesta en Marcha
1. Clonar el repositorio

git clone [https://github.com/tu-usuario/proyecto-NavarroMeza-JoseAntonio.git](https://github.com/tu-usuario/proyecto-NavarroMeza-JoseAntonio.git)
cd proyecto-NavarroMeza-JoseAntonio

2. Configurar variables de entorno
Crea tu archivo .env local a partir de la plantilla:

    cp .env.example .env

3. Construir y levantar la arquitectura

    # Validar sintaxis del archivo Compose
    docker compose config

    # Construir imágenes y levantar los 5 contenedores
    docker compose up -d --build

4. Verificar estado y salud

    docker compose ps

🌐 URLs de Acceso
    Aplicación Web (Frontend): http://localhost:8081

    Bandeja de Pruebas Mailpit (Web UI): http://localhost:8025

    Endpoint de Salud (Health Check): http://localhost:8081/api/health.php

🧪 Comandos para Verificación y Evidencias
Inspeccionar llaves y contador en Redis:

    docker compose exec redis redis-cli KEYS "*"
    docker compose exec redis redis-cli GET contador_consultas

Consultar registros directamente en MariaDB:

    docker compose exec db mariadb -u lab_user -padmin2026 inventario_db -e "SELECT * FROM equipos;"

Probar persistencia de datos (Recreación de contenedor sin perder datos):

    docker compose stop db
    docker compose rm -f db
    docker compose up -d db
    docker compose exec db mariadb -u lab_user -padmin2026 inventario_db -e "SELECT * FROM equipos;"

Inspeccionar la red y el volumen nombrado:

    docker network inspect proyecto-navarromeza-joseantonio_app_network
    docker volume inspect proyecto-navarromeza-joseantonio_mariadb_data

🛑 Detener la Aplicación
Detener contenedores conservando datos del volumen:

    docker compose down

Detener contenedores eliminando volúmenes (Reinicio completo desde cero)

    docker compose down -v