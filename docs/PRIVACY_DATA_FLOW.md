# Nik Voice Ticketing AI - Privacy & Data Flow Architecture

**Document Version**: 1.4.1  
**Last Updated**: October 2026  
**Engineering Organization**: Nik Neural AI Inc.  
**Classification**: Technical Privacy & Architecture Specification  

---

## 1. Scope & Objective

This document defines the comprehensive data flow, data classification inventory, storage lifecycle, transit controls, and single-provider isolation architecture implemented in **Nik Voice Ticketing AI (v1.4.1)**.

It provides technical transparency for website administrators, security engineers, and privacy officers assessing compliance with the **Personal Information Protection and Electronic Documents Act (PIPEDA)**, the British Columbia **Personal Information Protection Act (BC PIPA)**, and the **General Data Protection Regulation (GDPR)**.

> [!NOTE]
> This document describes technical system behaviors and verified controls. Technical controls assist organizations in meeting their privacy obligations but do not constitute legal advice or formal statutory compliance certification.

---

## 2. High-Level Data Flow Architecture

```mermaid
flowchart TD
    subgraph Browser ["Customer Browser Client"]
        Mic["Microphone Input (MediaStream)"]
        Recorder["MediaRecorder API (Opus/WebM)"]
        ClickTracker["Click & DOM Event Logger"]
        Dock["Recording Dock UI & Consent Notice"]
    end

    subgraph WordPress ["WordPress Host (On-Premise / Cloud)"]
        REST["WP REST API /nikvotia/v1/submit"]
        AudioStore["Hardened Storage wp-content/uploads/nikvotia/"]
        CoreDB["WordPress Database (Custom Post Type: voicedesk_ticket)"]
        StreamAPI["Authenticated Stream Endpoint /nikvotia/v1/audio/{ticket}"]
        Cron["Daily Retention Cleanup WP Cron"]
        PrivacyTools["WP Core Privacy Exporter & Eraser"]
    end

    subgraph AI_Engine ["Strictly Isolated AI Provider (Single-Engine Mode)"]
        NikCloud["Nik Voice Cloud Gateway (Groq Whisper-v3 Sovereign Canada)"]
        OpenAI["OpenAI API (Whisper-1 + GPT-4o-mini)"]
        Modulate["Modulate.ai API (Velma-2 Fast STT)"]
    end

    subgraph TelemetryServer ["Optional Diagnostic Telemetry"]
        Telemetry["nikneural.ca/tracking/ (Opt-In Only)"]
    end

    Mic --> Dock
    Dock --> Recorder
    ClickTracker --> REST
    Recorder -->|TLS 1.3 POST Audio Bitstream| REST
    REST -->|Hardened Storage (.htaccess blocked)| AudioStore
    REST -->|Ticket Metadata & Context| CoreDB
    REST -->|Single Engine Bound| AI_Engine
    AI_Engine -->|Transcript & Summary| CoreDB
    CoreDB -->|HMAC-SHA256 Token Auth| StreamAPI
    StreamAPI -->|HTTP 206 Partial Content Stream| Browser
    Cron -->|Unlinks Expired Audio| AudioStore
    PrivacyTools -->|Export / Erase Records| CoreDB
    WordPress -.->|Explicit Opt-In Only| TelemetryServer
```

---

## 3. Data Classification Inventory

The following inventory identifies every data element captured, processed, or stored by the plugin:

| Data Element | Classification | Source | Storage Location | Retention Lifecycle | Transit Protection |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Voice Audio Bitstream** | Sensitive Personal Data (Biometric / Voice) | Customer Microphone via `MediaRecorder` | `wp-content/uploads/nikvotia/` (Direct HTTP blocked) | Configurable: 0 to 365 days, or indefinite. Daily WP Cron auto-purge. | TLS 1.2 / TLS 1.3 HTTPS (negotiated by host) |
| **Voice Transcript** | Personal Data (Customer Communication) | AI Speech-to-Text Output | Database (`_nikvotia_transcript` postmeta) | Retained with ticket record until erased via WordPress Core Privacy Eraser. | TLS 1.2 / TLS 1.3 HTTPS |
| **AI Summary & Action Items** | Derivative Support Data | AI LLM Triage Output | Database (`_nikvotia_summary`, `_nikvotia_action_items`) | Retained with ticket record. | TLS 1.2 / TLS 1.3 HTTPS |
| **Customer Email** | Direct Personal Identifier (PII) | User Input or WP User Object | Database (`_nikvotia_user_email`) | Retained with ticket record. Included in Core Privacy Exporter/Eraser. | TLS 1.2 / TLS 1.3 HTTPS |
| **Customer Username / Display Name** | Direct Personal Identifier (PII) | User Input or WP User Object | Database (`_nikvotia_username`) | Retained with ticket record. | TLS 1.2 / TLS 1.3 HTTPS |
| **Client Environment (OS & Browser)** | Pseudonymous Technical Data | Client `navigator.userAgent` (Sanitized) | Database (`_nikvotia_environment`) | High-level family only (e.g. "Windows 11 / Chrome"). Stripped of hardware serials. | TLS 1.2 / TLS 1.3 HTTPS |
| **Clicked DOM Elements Timeline** | Telemetric Interaction Data | Frontend Click Event Listener | Database (`_nikvotia_clicked_elements`) | Tag names, truncated inner text (max 8 words), CSS selector, and time offset. | TLS 1.2 / TLS 1.3 HTTPS |
| **Page URL of Submission** | Contextual Support Data | `window.location.href` | Database (`_nikvotia_page_url`) | Retained with ticket record. | TLS 1.2 / TLS 1.3 HTTPS |
| **Cryptographic Stream Token** | Security Credential | Server generated (`hash_hmac`) | Dynamic computation (not persisted as stale meta) | Time-bounded (default 1 hour / 3600 seconds). Invalidated upon per-ticket salt rotation. Authorized sessions dynamically refresh URLs. | URL Query Parameter / TLS 1.2+ |
| **Diagnostic Telemetry Payload** | Non-PII Technical Data | WordPress Environment Options | Remote Telemetry Server (`nikneural.ca/tracking/`) | Disabled by default. Opt-in only. Zero customer data transmitted. | TLS 1.2 / TLS 1.3 HTTPS |

---

## 4. Strict Single-Provider AI Engine Isolation

To prevent unintended data transmission across multiple third parties, Nik Voice Ticketing AI enforces **Strict Single-Provider Isolation**:

1. **Deterministic Dispatch**: The administrator selects one primary AI provider under **VoiceDesk Tickets > Settings > AI Provider**:
   - `nik_voice_cloud`: Routes exclusively to Sovereign Canadian Gateway at `voice.nikneural.ca`.
   - `openai`: Routes exclusively to OpenAI API endpoints at `api.openai.com`.
   - `modulate_ai`: Routes exclusively to Modulate.ai API endpoints at `api.modulate.ai`.
2. **Zero Secondary Fallback**: If the selected provider encounters a network error or rate limit, the plugin **never** falls back to a secondary commercial API. Secondary providers remain completely dormant.
3. **On-Premise Local Triage Fallback**: If cloud triage fails or is unconfigured, the plugin falls back to `Nikvotia_Cloud_Client::sovereign_local_triage()`, an internal heuristic engine executing locally on the WordPress server with zero external network transmission.

---

## 5. Storage Protection & Access Control

1. **Upload Hardening**:
   - Audio files are stored under `wp-content/uploads/nikvotia/`.
   - Apache and LiteSpeed webservers enforce access denial via an auto-generated, idempotent `.htaccess` file containing managed block markers (`# BEGIN NikVoiceTicketingAI Protection`):
     ```apache
     # BEGIN NikVoiceTicketingAI Protection
     Options -Indexes
     <FilesMatch "(?i)\.(php|phtml|php3|php4|php5|php7|php8|phps|pht|phar|inc|cgi|pl|py|sh|bash|html|htm|svg|js|webm|ogg|oga|wav|mp3|m4a)$">
         Order allow,deny
         Deny from all
     </FilesMatch>
     <IfModule mod_authz_core.c>
         <FilesMatch "(?i)\.(php|phtml|php3|php4|php5|php7|php8|phps|pht|phar|inc|cgi|pl|py|sh|bash|html|htm|svg|js|webm|ogg|oga|wav|mp3|m4a)$">
             Require all denied
         </FilesMatch>
     </IfModule>
     <IfModule mod_headers.c>
         Header set X-Robots-Tag "noindex, nofollow, noarchive"
         Header set X-Content-Type-Options "nosniff"
     </IfModule>
     # END NikVoiceTicketingAI Protection
     ```
   - Direct requests for `.webm`, `.wav`, `.mp3`, or `.htaccess` return `HTTP 403 Forbidden`.
   - **Filesystem Encryption Notice**: The web server configuration files (`.htaccess`) and filesystem permissions enforce network access control to prevent unauthorized HTTP requests. Audio recordings on disk are unencrypted at the application layer. Organizations requiring encryption at rest must implement host-level or volume-level encryption (such as Linux LUKS, Windows BitLocker, or cloud provider encrypted volumes).
2. **Authenticated Streaming**:
   - Direct physical URLs are completely purged from HTML templates.
   - Playback is delivered via `GET /wp-json/nikvotia/v1/audio/{ticket_number}` requiring administrative capabilities, ticket author authentication, or a valid time-bounded cryptographic HMAC-SHA256 token (default 1-hour lifetime).
   - Authorized administrative and customer portal views automatically invoke server-side dynamic URL refresh (`Nikvotia_API::get_authenticated_stream_url()`).
   - Stream responses deliver `X-Robots-Tag: noindex, nofollow, noarchive` to prevent search engine indexing.

---

## 6. Data Retention & Erasure Integration

1. **Automated Audio Pruning (Storage Limitation)**:
   - Configurable retention periods: 0 days (instant purge post-transcription), 7 days, 14 days, 30 days, 60 days, 90 days, 180 days, 365 days, or indefinite.
   - Scheduled WP Cron (`nikvotia_daily_data_retention_cleanup`) runs daily, identifying audio files exceeding the retention window, physically unlinking them from disk, and clearing file references from postmeta while preserving anonymized ticket text.
2. **WordPress Core Privacy Tools**:
   - **Personal Data Exporter** (`Tools > Export Personal Data`): Registers callback with `wp_privacy_personal_data_exporters` exporting all ticket numbers, submission dates, transcripts, summaries, and audio stream links associated with a customer email address.
   - **Personal Data Eraser** (`Tools > Erase Personal Data`): Registers callback with `wp_privacy_personal_data_erasers` permanently deleting physical audio files, clearing postmeta, and removing ticket records for the requested email address.
