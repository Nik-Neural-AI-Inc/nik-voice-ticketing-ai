# Nik Voice Ticketing AI - Privacy Gap Analysis & Environmental Limitations

**Document Version**: 1.4.1  
**Last Updated**: October 2026  
**Engineering Organization**: Nik Neural AI Inc.  
**Classification**: Compliance Transparency & Security Advisory  

---

## 1. Executive Summary & Legal Disclaimer

This document identifies environmental dependencies, architectural boundaries, and operational considerations relevant to organizations deploying **Nik Voice Ticketing AI (v1.4.1)** in jurisdictions governed by privacy legislation, including:
- **PIPEDA** (Personal Information Protection and Electronic Documents Act - Canada)
- **BC PIPA** (Personal Information Protection Act - British Columbia)
- **GDPR** (General Data Protection Regulation - Regulation (EU) 2016/679)

> [!IMPORTANT]
> **Legal Disclaimer**: Nik Neural AI Inc. provides software engineering tools designed with privacy-by-design safeguards. However, technical software features alone do **not** constitute legal certification, statutory compliance, or legal advice. Statutory compliance requires organizational policies, employee training, data processing agreements (DPAs), and proper server configuration that reside entirely within the responsibility of the data controller (site owner).

---

## 2. Identified Privacy Safeguards vs. Operational Gaps

| Privacy Domain | Implemented Technical Safeguard | Remaining Environmental or Operational Gap | Recommended Action for Data Controller |
| :--- | :--- | :--- | :--- |
| **Direct Audio Access** | Auto-managed `.htaccess` with `# BEGIN NikVoiceTicketingAI Protection` blocking audio extensions and script execution on Apache/LiteSpeed. | **Webserver Dependency**: Nginx, Microsoft IIS, and Caddy do not parse `.htaccess` files by default. | Site operators running Nginx, IIS, or Caddy must manually apply the server directives documented in `README.md`. |
| **Audio Stream Protection** | Time-bounded HMAC-SHA256 tokens (`?token=`) with 1-hour TTL, dynamic URL refresh for authorized sessions, and instant per-ticket salt rotation revocation. | **Server Access Logs**: Standard Apache/Nginx access logs record complete query strings, potentially storing token strings in access log files. | Configure webserver access log formatting to strip or sanitize query parameters from `/wp-json/nikvotia/v1/audio/` log entries, or implement log rotation/encryption. |
| **Storage Limitation** | Configurable automatic audio retention window (0-365 days) with daily scheduled WP Cron physical file unlinking. | **Cron Reliability**: If WordPress Cron is not triggered by site traffic or a system cron daemon, pruning could be delayed. | Configure system-level `crontab` calling `wp-cron.php` every 15 minutes to ensure timely execution of data retention tasks. |
| **Data at Rest Encryption** | File permissions restricted to web server user; direct HTTP access denied. | **Host Disk Encryption**: Software does not perform native file-level symmetric encryption on physical audio files stored in `uploads/nikvotia/`. | Deploy WordPress on an operating system with full-disk encryption (LUKS, BitLocker) and configure encrypted database storage. |
| **Shared Hosting Risks** | File system separation via WordPress upload directory paths. | **Multi-Tenant Server Isolation**: On shared hosting with improper `umask` or shared PHP processes, other local users might inspect files directly on disk. | Host production environments on dedicated virtual private servers (VPS), containers, or enterprise hosts with strict tenant isolation. |
| **Third-Party AI Processing** | Deterministic single-provider isolation; inactive APIs remain completely dormant. | **Data Processor Terms**: When using commercial third-party APIs (OpenAI or Modulate.ai), audio leaves the site server for processing in external jurisdictions (USA). | Execute formal Data Processing Agreements (DPAs) with OpenAI or Modulate.ai, or select the Sovereign Canadian Cloud gateway (`voice.nikneural.ca`). |
| **Data Erasure Rights** | Full integration with WordPress Core Personal Data Exporter and Personal Data Eraser. | **Off-Site Backups**: Site backups created by external backup plugins or hosting snapshots will contain audio copies created prior to erasure. | Document backup retention cycles in the organization's privacy policy and ensure deleted data is cycled out of disaster recovery snapshots within statutory timeframes. |
| **Consent & Transparency** | Frontend recording dock consent banner with configurable disclosure text. | **User Acknowledgment**: Voice recordings submitted without explicit affirmative checkbox toggle (banner is informational unless customized). | Update terms of service and customize the recording consent text under VoiceDesk Settings > Privacy to reflect corporate data handling policies. |

---

## 3. Server-Level Protection Gaps

### 3.1 Apache & LiteSpeed Environments
The plugin automatically writes and maintains protection rules in `wp-content/uploads/nikvotia/.htaccess`. However:
- If Apache is configured with `AllowOverride None`, the webserver ignores `.htaccess` entirely.
- **Verification Requirement**: Administrators must test direct file retrieval against a synthetic audio file (e.g. `GET /wp-content/uploads/nikvotia/sample.webm`) and verify that Apache returns `HTTP 403 Forbidden`.

### 3.2 Nginx Environments
Nginx does not read `.htaccess` files. Without manual configuration, audio files in `wp-content/uploads/nikvotia/` will be served directly by Nginx without authentication.
- **Mandatory Directive**:
  ```nginx
  location ^~ /wp-content/uploads/nikvotia/ {
      deny all;
      return 403;
  }
  ```

### 3.3 Microsoft IIS & Caddy Environments
Similar to Nginx, administrators must manually insert request filtering rules into `web.config` or handlers into `Caddyfile` as documented in `README.md`.

---

## 4. Multi-Tenant & Shared Hosting Limitations

In shared hosting environments:
1. Multiple customers share the same underlying operating system kernel and filesystem.
2. If the hosting provider does not configure `open_basedir`, PHP-FPM pool isolation, or containerization, local users on the same server might bypass `.htaccess` and read audio files directly from the filesystem.
3. Organizations handling sensitive financial, medical, or legal voice recordings should avoid shared hosting and deploy on dedicated, isolated infrastructure.

---

## 5. Recommended Actions for Data Controllers

To establish an enterprise-grade compliance posture:

1. **Conduct a Privacy Impact Assessment (PIA)**: Evaluate voice recording risks in light of specific provincial (e.g. BC PIPA, Alberta PIPA, Quebec Law 25) and international legislation.
2. **Review Third-Party Sub-Processors**: Review OpenAI and Modulate.ai data retention terms, or select the Sovereign Canadian Cloud (`voice.nikneural.ca`) to maintain Canadian data residency.
3. **Verify Webserver Enforcement**: Execute automated regression test `TEST-01` to confirm that direct HTTP access to audio returns `403 Forbidden`.
4. **Establish Retention Policies**: Configure the Audio Data Retention Window in VoiceDesk Settings according to organizational data retention schedules.
5. **Formal Legal Review**: Consult with qualified legal counsel specializing in Canadian and European privacy law prior to public launch.
