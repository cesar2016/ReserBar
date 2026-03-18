# 🍽️ ReserBar

### *Sistema de Reservas para Comercios Gastronómicos*

<p align="center">
  <img src="https://img.shields.io/badge/React-19.2.4-61DAFB?style=for-the-badge&logo=react" alt="React">
  <img src="https://img.shields.io/badge/Laravel-12.54.1-FF2D20?style=for-the-badge&logo=laravel" alt="Laravel">
  <img src="https://img.shields.io/badge/MySQL-8.0-4479A1?style=for-the-badge&logo=mysql" alt="MySQL">
  <img src="https://img.shields.io/badge/Redis-6.0.16-DC382D?style=for-the-badge&logo=redis" alt="Redis">
  <img src="https://img.shields.io/badge/Groq-ChatBox-FF6B6B?style=for-the-badge" alt="Groq AI">
</p>

---

## 📋 Descripción

**ReserBar** es una aplicación moderna para la gestión de reservas en comercios gastronómicos. Permite a los usuarios registrar sus reservas de manera eficiente y consultar disponibilidad en tiempo real.

### ✨ Características Principales

| Característica | Descripción |
|----------------|-------------|
| 🔐 **Autenticación** | Registro e inicio de sesión con JWT (Sanctum) |
| 📅 **Gestión de Reservas** | Crear, editar, eliminar y visualizar reservas |
| 🪑 **Estado de Mesas** | Vista en tiempo real del estado de ocupación |
| ⏱️ **Countdown Timer** | Contador regresivo para cada reserva activa |
| 🤖 **ChatBot IA** | Asistente virtual con tecnología Groq para reservas fluidas |
| 📱 **Diseño Responsive** | Interfaz adaptable a cualquier dispositivo |

---

## 🛠️ Stack Tecnológico

### Frontend
```
🟢 NodeJS v20.12.2
⚛️  React 19.2.4
📦 Context API para gestión de estado
🎨 Tailwind CSS + CSS Variables
📡 Axios para comunicación con API
```

### Backend (API REST)
```
🟣 PHP 8.3
🎼 Laravel Framework 12.54.1
🔐 Laravel Sanctum (Autenticación)
📊 Prisma ORM
```

### Base de Datos
```
🐬 MySQL 8.0
⚡ Redis 6.0.16 (Caché de alto rendimiento)
```

---

## 🚀 Cómo Levantar el Sistema

### Prerrequisitos

- Git
- Node.js v20.12.2+
- PHP 8.3+
- Composer 2.8+
- MySQL 8.0
- Redis 6.0+

### 1. Clonar el Repositorio

```bash
git clone git@github.com:cesar2016/ReserBar.git
cd ReserBar
```

### 2. Configurar el Backend (Laravel)

```bash
cd backend

# Instalar dependencias
composer install

# Copiar archivo de configuración
cp .env.example .env

# Generar clave de aplicación
php artisan key:generate

# Generar clave JWT
php artisan jwt:secret

# Ejecutar migraciones
php artisan migrate

# Poblar base de datos con datos iniciales
php artisan db:seed

# Limpiar caché de Redis
redis-cli FLUSHDB
```

### 3. Configurar el Frontend (React)

```bash
cd ../frontend

# Instalar dependencias
npm install

# Iniciar servidor de desarrollo
npm run dev
```

### 4. Iniciar Servicios

```bash
# Terminal 1 - Backend (Puerto 8000)
cd backend
php artisan serve --host=0.0.0.0 --port=8000

# Terminal 2 - Frontend (Puerto 3535)
cd frontend
npm run dev
```

### 🌐 Acceso a la Aplicación

| Servicio | URL |
|----------|-----|
| Frontend | http://localhost:3535 |
| Backend API | http://localhost:8000 |

### 🔑 Credenciales por Defecto

```
📧 Email: admin@reserbar.com
🔐 Contraseña: password123
```

---

## 🤖 Asistente Virtual (ChatBox IA)

El sistema incluye un **ChatBot inteligente** integrado con **Groq AI** que permite:

- 💬 Realizar reservas de forma conversacional
- 🔍 Consultar disponibilidad de mesas
- 📅 Consultar horarios del restaurante
- 📍 Obtener información de ubicación
- ❓ Resolver dudas frecuentes

### Modelos de IA Disponibles

| Modelo | Descripción |
|--------|-------------|
| Llama 3.1 70B | Mayor capacidad de razonamiento |
| Llama 3.1 8B | Respuestas más rápidas |

---

## 📊 Modelo de Datos

```
┌─────────────┐       ┌────────────────┐       ┌─────────────┐
│   Users     │       │  Reservations  │       │   Tables    │
├─────────────┤       ├────────────────┤       ├─────────────┤
│ id          │──────<│ id             │>──────│ id          │
│ name        │       │ date           │       │ location    │
│ email       │       │ time           │       │ number      │
│ password    │       │ duration       │       │ capacity    │
│ created_at  │       │ user_id        │       │ created_at  │
│ updated_at  │       │ table_ids      │       │ updated_at  │
└─────────────┘       │ guest_count    │       └─────────────┘
                      │ created_at     │
                      │ updated_at     │
                      └────────────────┘
```

---

## ⚙️ Configuración de Redis

El sistema utiliza **Redis** para caché de alto rendimiento:

```bash
# Verificar conexión
redis-cli ping
# Respuesta: PONG

# Monitorear consultas en tiempo real
redis-cli monitor

# Ver claves de caché
redis-cli KEYS "*"

# Limpiar caché
redis-cli FLUSHDB
```

---

## 🔄 Comandos Útiles

```bash
# Reiniciar base de datos completa
php artisan migrate:fresh --seed
redis-cli FLUSHDB

# Ver rutas de la API
php artisan route:list

# Limpiar caché de Laravel
php artisan cache:clear
php artisan config:clear

# Abrir Prisma Studio (si está configurado)
npx prisma studio
```

---

## 👨‍💻 Desarrollador

<p align="center">
  <img src="https://img.shields.io/badge/Desarrollado_por-Cesar_R_Sanchez-0077B5?style=for-the-badge&logo=linkedin" alt="LinkedIn">
</p>

### **Cesar R. Sanchez**
*Full Stack Developer*

<p align="center">
  <a href="https://www.linkedin.com/in/cesar-sanchez-dev/">
    <img src="https://img.shields.io/badge/LinkedIn-0077B5?style=for-the-badge&logo=linkedin&logoColor=white" alt="LinkedIn">
  </a>
  <a href="mailto:cesars.pro@gmail.com">
    <img src="https://img.shields.io/badge/Email-D14836?style=for-the-badge&logo=gmail&logoColor=white" alt="Email">
  </a>
</p>

---

## 📝 Licencia

Este proyecto está bajo la Licencia MIT - ver el archivo [LICENSE](LICENSE) para más detalles.

---

<p align="center">
  <strong>Hecho con ❤️ y ☕ por Cesar R. Sanchez</strong>
  <br>
  <sub>ReserBar - Reservas gastronómicas simplificadas</sub>
</p>
