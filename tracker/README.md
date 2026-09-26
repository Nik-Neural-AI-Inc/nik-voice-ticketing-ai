# Nik VoiceDesk AI — Standalone Telemetry Tracker

Deploy this single file to your server to track all WordPress installations running **Nik VoiceDesk AI**.

## 📁 Deployment to https://aboozaresmaili.com/tracking/

1. Upload index.php to your web server in the /tracking/ folder:
   `
   https://aboozaresmaili.com/tracking/index.php
   `
2. Ensure PHP has write permissions to create 	racking.sqlite in the same directory.
   - On most servers (cPanel, LiteSpeed, Apache, Nginx), no configuration is needed.
   - SQLite is built directly into PHP by default.

---

## 🔒 Accessing Your Dashboard

- URL: https://aboozaresmaili.com/tracking/
- Default Administrator Password: 
iktelemetry2026
  *(You can change TRACKER_PASSWORD directly at the top of index.php)*
- Direct Link with key: https://aboozaresmaili.com/tracking/?pass=niktelemetry2026

---

## 🚀 Features

- **No Big Database Needed**: Uses a high-performance, single-file SQLite database (	racking.sqlite) with WAL mode enabled.
- **Auto Upsert**: When a domain pings again, its last seen date, ping count, and versions are automatically updated (no duplicate domain records).
- **KPI Metrics**: Total sites, active in last 7 days, Enterprise Pro vs Free installations, and total pings.
- **Search & Filter**: Real-time client-side search by domain, email, and server. Filter by active/inactive or plan.
- **CSV Export**: Download full telemetry records with one click.
- **Dark / Light Mode**: Seamless built-in theme toggle.