# 🥐 Bakery Shop Management System (BSMS)

A modern, responsive, and robust **Bakery Shop Management System** built with PHP and MySQL, fully configured for seamless cloud deployment on **Render** using Docker and **Aiven Cloud MySQL** with SSL encryption.

---

## 🚀 Cloud Deployment Features

- **PHP 8.2 + Apache Docker Environment**: Pre-configured with `mysqli`, `pdo_mysql`, `gd`, `zip`, `rewrite`, and `headers`.
- **Aiven Cloud MySQL SSL Enforcement**: Built-in `MYSQLI_CLIENT_SSL` and custom 5-digit port support (`require_secure_transport=ON`).
- **Render Reverse Proxy & Port 10000 Fixes**: Apache configured with `UseCanonicalName Off`, `UseCanonicalPhysicalPort Off`, dynamic `$PORT` binding, and direct routing preventing internal port leak loops.
- **1-Click Web Database Setup Tool (`/db_setup.php`)**: Visual installer with real-time health check, SSL cipher status, automated table parsing, and admin user verification.
- **PHP 8.x Compatibility**: Safe property initialization, safe object checks (`mysqli_result`), and parameterized connection methods.

---

## 🔑 System Login Credentials (ID & Passwords)

> [!IMPORTANT]
> ### 👑 Primary Admin Login:
> - **Username (ID):** `admin`
> - **Password:** `admin123`

| Role | Username (ID) | Password | Access Level |
| :--- | :--- | :--- | :--- |
| **👑 Administrator** | `admin` | `admin123` | Full Access (POS, Stock, Categories, Users, Maintenance) |
| **👤 Claire Blake (Staff)** | `cblake` | `cblake` | POS & Sales Reports |
| **👤 Mark Cooper (Admin)** | `mcooper` | `mcooper` | Full Admin Access |

---

## 🌐 1-Click Database Setup & Importer

After deploying to Render, visit the built-in database setup tool at:
```
https://<your-render-app-url>.onrender.com/db_setup.php
```
- Real-time connection ping & SSL cipher validation.
- Click **"1-Click Import DB"** to import `database/bsms_db.sql` automatically with foreign key checks managed.
- Verify table rows and click **"Open Application Login"** to start using the system immediately.

---

## ⚙️ Environment Variables (Render & Cloud MySQL)

Configure the following environment variables in your Render Web Service dashboard under the **Environment** tab:

| Variable | Description | Example Value |
| :--- | :--- | :--- |
| `DB_SERVER` | Aiven MySQL Service URI / Host | `mysql-xxxxx-project.aivencloud.com` |
| `DB_PORT` | Custom MySQL Port | `26284` |
| `DB_USERNAME` | MySQL Username | `avnadmin` |
| `DB_PASSWORD` | MySQL User Password | `AVNS_xxxxxxxxxxxx` |
| `DB_NAME` | MySQL Database Name | `defaultdb` |
| `DB_SSL` | Enforce SSL for Aiven Cloud | `true` |
| `PORT` | Container Listen Port (Render dynamic) | `10000` |

> *Alternative*: You can also set a single `DATABASE_URL` / `MYSQL_URL` string:  
> `mysql://avnadmin:password@mysql-xxxxx.aivencloud.com:26284/defaultdb?ssl-mode=REQUIRED`

---

## 🛠️ Step-by-Step Render Deployment Guide

1. **Fork or Push to GitHub**:
   Ensure this repository is pushed to your GitHub account on branch `main`.

2. **Create New Web Service on Render**:
   - Go to [dashboard.render.com](https://dashboard.render.com/) and click **New +** -> **Web Service**.
   - Connect your GitHub repository: `Bakery-Shop-Management-System`.
   - Environment: Select **Docker** (Render will automatically detect `Dockerfile`).
   - Region: Select your preferred region (e.g., Oregon or Singapore).
   - Plan: **Free** or any standard tier.

3. **Set Environment Variables**:
   Under the **Environment Variables** section, add your Aiven MySQL credentials:
   - `DB_SERVER`
   - `DB_PORT`
   - `DB_USERNAME`
   - `DB_PASSWORD`
   - `DB_NAME`
   - `DB_SSL` = `true`

4. **Deploy Service**:
   - Click **Create Web Service**.
   - Render will build the Docker image and launch Apache on `$PORT`.

5. **Initialize Database**:
   - Once deployed, open `https://<your-app>.onrender.com/db_setup.php`.
   - Click **"1-Click Import DB"**.
   - Login with `admin` / `admin123`.

---

## 💻 Local Development with Docker

Run locally with Docker in seconds:

```bash
# Build the Docker image
docker build -t bsms-app .

# Run container on local port 8080
docker run -d -p 8080:10000 \
  -e PORT=10000 \
  -e DB_SERVER=localhost \
  -e DB_PORT=3306 \
  -e DB_USERNAME=root \
  -e DB_PASSWORD= \
  -e DB_NAME=bsms_db \
  --name bsms-container bsms-app
```

Then open `http://localhost:8080` in your browser.

---

## 📁 Repository Structure

```
.
├── .dockerignore
├── .gitignore
├── .htaccess                  # Reverse proxy trailing slash & port protection
├── Dockerfile                 # PHP 8.2 Apache production image
├── render.yaml                # Render Blueprint deployment definition
├── start.sh                   # Dynamic port binding & Apache proxy configuration
├── DBConnection.php           # Aiven SSL & custom port MySQL connection class
├── db_setup.php               # 1-Click web database installer & health check
├── index.php                  # Application dashboard & navigation router
├── login.php                  # Authentication UI
├── Actions.php                # Backend AJAX controller & API endpoints
├── database/
│   └── bsms_db.sql            # Clean MySQL schema & sample records
├── css/, js/, images/         # Frontend assets (Bootstrap, jQuery, Select2, DataTables)
└── README.md                  # Comprehensive setup & deployment documentation
```

---

## 🛡️ License & Credits
Source code distributed for educational and commercial bakery management purposes.
