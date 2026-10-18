# Nik Voice Ticketing AI

[![Version](https://img.shields.io/badge/version-1.3.8-blue.svg)](https://nikneural.ca/voicedesk-ai/)
[![WordPress.org](https://img.shields.io/badge/WordPress.org-nik--voice--ticketing--ai-0073aa.svg?logo=wordpress&logoColor=white)](https://wordpress.org/plugins/nik-voice-ticketing-ai/)
[![GitHub](https://img.shields.io/badge/GitHub-Repository-181717.svg?logo=github)](https://github.com/Nik-Neural-AI-Inc/nik-voice-ticketing-ai)
[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.svg)](LICENSE)
[![Privacy by Design](https://img.shields.io/badge/Privacy-By%20Design-green.svg)](https://nikneural.ca/)
[![Nik Neural AI Inc.](https://img.shields.io/badge/Developed%20by-Nik%20Neural%20AI%20Inc.-orange.svg)](https://nikneural.ca/)

**Nik Voice Ticketing AI** is a production-ready, zero-friction, AI-powered voice ticketing system for WordPress developed by **Nik Neural AI Inc.** It enables website visitors and logged-in customers to record voice messages, automatically transcribe them using advanced Speech-to-Text models (OpenAI Whisper or Modulate.ai), intelligently categorize and summarize the issue using Large Language Models, and track click interactions on the page during the recording session.

---

## Audio Storage, Streaming Security & Server Directives

Voice recordings are stored securely under `wp-content/uploads/nikvotia/` and protected by a multi-layered defense-in-depth architecture.

### 1. Direct Webserver Access Prevention
Direct HTTP/HTTPS access to physical audio files (`.webm`, `.ogg`, `.oga`, `.wav`, `.mp3`, `.m4a`) as well as executable scripts (`.php`, `.html`, etc.) is strictly prohibited.

#### Apache & LiteSpeed
Direct access is automatically blocked via an auto-maintained `.htaccess` file deployed directly to `wp-content/uploads/nikvotia/`:
```apache
<IfModule mod_authz_core.c>
    <FilesMatch "(?i)\.(php|phtml|php3|php4|php5|php7|php8|phps|pht|phar|inc|cgi|pl|py|sh|bash|html|htm|svg|js|webm|ogg|oga|wav|mp3|m4a)$">
        Require all denied
    </FilesMatch>
</IfModule>
<IfModule !mod_authz_core.c>
    <FilesMatch "(?i)\.(php|phtml|php3|php4|php5|php7|php8|phps|pht|phar|inc|cgi|pl|py|sh|bash|html|htm|svg|js|webm|ogg|oga|wav|mp3|m4a)$">
        Deny from all
    </FilesMatch>
</IfModule>
<IfModule mod_headers.c>
    Header set X-Robots-Tag "noindex, nofollow, noarchive"
    Header set X-Content-Type-Options "nosniff"
</IfModule>
```

#### Nginx
For Nginx webservers, add the following location block to your server configuration:
```nginx
location ^~ /wp-content/uploads/nikvotia/ {
    deny all;
    return 403;
}
```

#### Microsoft IIS (`web.config`)
For IIS environments, place the following rule in your `web.config`:
```xml
<configuration>
  <system.webServer>
    <security>
      <requestFiltering>
        <hiddenSegments>
          <add segment="wp-content/uploads/nikvotia" />
        </hiddenSegments>
      </requestFiltering>
    </security>
  </system.webServer>
</configuration>
```

#### Caddy
For Caddy servers, add the following handler to your Caddyfile:
```caddy
@blocked path /wp-content/uploads/nikvotia/*
handle @blocked {
    respond "Access Denied" 403
}
```

### 2. Authenticated Audio Streaming Endpoint
All audio playback and file downloads are mediated strictly through the authenticated REST API endpoint:
- `GET /wp-json/nikvotia/v1/audio/{ticket_number}` (Alias: `/wp-json/nik-voice/v1/audio/{ticket_number}`)

Access is granted if and only if one of the following criteria is met:
1. **Administrative Access**: User has `edit_post` or `manage_options` capability.
2. **Ticket Author Access**: User is logged in as the WordPress user who created the ticket (and the ticket is not closed).
3. **Cryptographic HMAC-SHA256 Token**: Request includes a valid, time-bounded, cryptographically signed token in the `token` parameter (and the ticket is not closed).

### 3. Cryptographic Token Architecture (HMAC-SHA256)
- **Algorithm**: `HMAC-SHA256` computed via `hash_hmac('sha256', $payload, $secret)`.
- **Payload**: `{ticket_number}|{post_id}|{expires}|{ticket_salt}`
- **Token Format**: `{expires}.{signature}` (transmitted via `?token=`).
- **Cryptographic Secret**: Site-specific 64-character persistent secret combined with WordPress core `AUTH_SALT`.
- **Per-Ticket Salt**: Unique 16-character salt stored in `_nikvotia_ticket_salt`.
- **Time-to-Live (TTL)**: Configured with a default TTL of 7 days (604,800 seconds). Developers can adjust this duration using the `nikvotia_audio_token_ttl` filter.
- **Instant Revocation**:
  - Rotating the per-ticket salt or clicking "Revoke Link" in the Admin Ticket Console immediately invalidates all previously issued HMAC tokens.
  - Setting a ticket to **Closed** immediately blocks audio streaming access for guests and non-staff.
- **Seeking & Downloads**:
  - Supports HTTP `206 Partial Content` Range requests (`Accept-Ranges: bytes`) for smooth audio seeking in browser players.
  - Supports `?download=1` query argument for direct attachment downloads with sanitized filenames (`ticket-ID-recording.ext`).
  - Strict security headers on all stream responses: `X-Robots-Tag: noindex, nofollow, noarchive` and `X-Content-Type-Options: nosniff`.

---

## Key Features

### Zero-Friction Voice Recording
- **Vanilla JS Floating Mic Button**: Lightweight, ultra-responsive microphone trigger positioned cleanly on the screen.
- **Modern Recording Dock**: Real-time recording indicator with pulsating red dot, live elapsed timer, and dynamic animated audio waveform.
- **Automatic Audio Processing**: Audio is packaged in web-standard audio formats (`audio/webm` with Opus codec, fallback to WAV/OGG) and transmitted securely via the WordPress REST API.

### Interactive Click Tracking & Visual Pinning
- Captures DOM element clicks, CSS selectors, coordinates `(X, Y)`, element tags, and millisecond time offsets during voice recording.
- Automatically places temporary visual numbered ripple pins on clicked elements so the user sees their clicks being logged.
- Disables link navigation and form submissions while recording to ensure uninterrupted voice capture.

### Bring Your Own Key (BYOK) Multi-Model AI Engine
- **Speech-to-Text (STT)**:
  - **OpenAI Whisper** (`whisper-1`): High-accuracy global speech recognition.
  - **Modulate.ai API**: Ultra-fast voice transcription and toxicity/emotion analysis.
- **Language Models (LLM)**:
  - Supports `gpt-4o-mini`, `gpt-4o`, `gpt-3.5-turbo`, etc., for automated summaries, issue categorization, and department routing.
- **Direct WordPress to AI Communication**: Uses `wp_remote_post` without external middleware, maintaining strict data sovereignty.

### Unique Ticket ID & Confirmation Email
- Every voice submission generates a unique Ticket ID (e.g., `#VD-1LSS67KO`).
- A dedicated, non-disappearing success modal presents the Ticket ID, assigned department, and AI summary, along with a 1-click "Copy ID" button.
- Automatically sends a confirmation email to the user with their Ticket ID and customer portal link.

### Unified Admin Ticket Console & Settings
- **Clean Two-Column Dashboard**: View audio recording with custom playback, download link, AI executive summary, full voice transcript, user environment (sanitized OS and Browser), and visual click timeline.
- **Dark Mode & Light Mode**: Built-in toggle with persistent preferences via `localStorage`.
- **Deduplicated Reply System**: Reply to customers with automatic email notifications. Includes built-in duplicate detection and Post-Redirect-Get (PRG) patterns to eliminate duplicate messages or emails on page refresh.

### Customer Ticket Portal & WooCommerce Integration
- **Shortcode `[nikvotia_tickets]`**: Embed a dedicated ticket portal anywhere on your site allowing customers to view status, listen to voice notes, and reply to staff.
- **WooCommerce My Account Endpoint**: Seamlessly registers a new "Voice Support Tickets" tab in the WooCommerce customer dashboard.

### Advanced Visibility & Audience Controls
- Control which pages, posts, or custom post types display the floating recording button.
- Restrict visibility by user status: All visitors, logged-in users only, logged-out users only, or specific User IDs.

### Enterprise Privacy, Data Retention & Consent Controls (PIPEDA & GDPR)
- **Automated Audio Lifecycle Pruning**: Configurable retention window (from 0 to 365 days, or indefinite) with scheduled daily WP Cron pruning (`nikvotia_daily_data_retention_cleanup`) and on-demand manual purge. Expired audio files are physically unlinked from server disk while retaining anonymized text tickets.
- **Native WordPress Privacy Integration**: Registered with WordPress Core Privacy Tools:
  - **Export Personal Data** (`Tools > Export Personal Data`): Full data portability for customer voice tickets, summaries, transcripts, and audio references.
  - **Erase Personal Data** (`Tools > Erase Personal Data`): Comprehensive right-to-be-forgotten eraser permanently purging voice recordings, metadata, and ticket posts.
- **Transparent Frontend Consent Banner**: Configurable voice processing and AI transcription disclosure displayed cleanly on the frontend recording dock.
- **Zero-PII Diagnostic Telemetry**: Administrator email exclusion toggle ensuring telemetry transmissions contain zero personal identifiers.
- **Audio Stream Security**: Authenticated REST endpoint delivery with anti-IDOR cryptographic tokens, upload directory traversal containment, and `X-Robots-Tag: noindex, nofollow, noarchive` search index suppression.

---

## Requirements

- **WordPress**: 5.8 or higher
- **PHP**: 7.4 or higher (fully tested on PHP 8.0, 8.1, and 8.2)
- **SSL Certificate**: HTTPS is required for modern browsers to grant microphone permissions (`getUserMedia`).
- **cURL Extension**: Enabled in PHP for outbound REST API requests.

---

## Installation & Setup

1. **Upload Plugin**:
   - Download the `nik-voice-ticketing-ai` directory or release archive.
   - Upload it to your WordPress `/wp-content/plugins/` directory.
2. **Activate Plugin**:
   - Navigate to **Plugins** in the WordPress Admin and activate **Nik Voice Ticketing AI**.
3. **Configure Settings**:
   - Navigate to **VoiceDesk Tickets > Settings** in the WordPress admin menu.
   - **General Tab**: Select your customer portal page and define button visibility rules.
   - **AI Integrations Tab**: Choose your STT model (Nik Voice Cloud, OpenAI Whisper, or Modulate.ai) and enter your API keys.
   - **Appearance Tab**: Customize the button color, icon color, and tooltip text.
   - **License Tab**: Enter your Enterprise Pro license key to remove all branding.

---

## Shortcodes

### Customer Ticket Portal
```text
[nikvotia_tickets]
```
Add this shortcode to any WordPress page (e.g., `/support/` or `/my-tickets/`) to provide users with a clean, responsive portal to track their tickets and reply to staff.
*(Backward-compatible shortcode alias: `[nik_voicedesk_tickets]`)*

---

## Privacy & Compliance (PIPEDA by Default)

Nik Voice Ticketing AI was designed by **Nik Neural AI Inc.** following the principles of Canadian privacy compliance (PIPEDA):
- No third-party SaaS middleware is used; all API requests go directly from your WordPress server to your chosen AI provider.
- User environment details are sanitized to only include high-level OS and Browser names, stripping hardware and browser fingerprinting data.
- Voice audio files are stored securely in WordPress uploads and streamed via authenticated REST endpoints.

---

## Official Links & Corporate Information

- **Company**: **Nik Neural AI Inc.**  
  112-970 Burrard Street, Office# 1760  
  Vancouver, BC V6Z 2R4, Canada  
  Phone: (604) 283-7353  
- **Official Website**: [https://nikneural.ca/](https://nikneural.ca/)  
- **Product Page**: [https://nikneural.ca/voicedesk-ai/](https://nikneural.ca/voicedesk-ai/)  
- **WordPress.org Plugin**: [https://wordpress.org/plugins/nik-voice-ticketing-ai/](https://wordpress.org/plugins/nik-voice-ticketing-ai/)  
- **GitHub Repository**: [https://github.com/Nik-Neural-AI-Inc/nik-voice-ticketing-ai](https://github.com/Nik-Neural-AI-Inc/nik-voice-ticketing-ai)  
- **Contributors**: `nikneuralaiinc`, `aboozaresmaili`

## Changelog

### 1.3.8
* **Fix & Accessibility**: Overhauled Customer Ticket Portal and Single Ticket View with ultra-high contrast styling in both Dark and Light modes, eliminating faint/invisible text caused by external theme stylesheet bleeding.
* **Feature**: Added smart automatic theme detection (system preference, theme dark classes, and body background luminance) and seamless Dark/Light mode switcher toggle for the single ticket detail view.
* **Fix**: Resolved modal "View Ticket" endless page reload bug by implementing automatic provisioning for the default portal page when WooCommerce is inactive.
* **Security & Routing**: Enforced authentication-aware routing in customer portal: unauthenticated guests are securely redirected to login with safe `redirect_to` parameter, and guest tickets auto-bind to customer accounts upon login.
* **Packaging**: Removed development/CI test suite folder from production distribution archive to deliver a lightweight, clean package for end users.

### 1.3.7
* **Audio Access Protection (SEC-02 & SEC-03)**: Hardened `wp-content/uploads/nikvotia/` via automated `.htaccess` rules denying direct HTTP access to audio file extensions (`webm|ogg|oga|wav|mp3|m4a`) and enforcing `X-Robots-Tag: noindex, nofollow, noarchive`. Added Nginx, IIS, and Caddy directives for non-Apache environments.
* **Direct URL Leak Elimination (SEC-04)**: Fully eliminated physical storage URLs (`_nikvotia_audio_url`) from rendered admin console and frontend customer portal HTML templates, routing all playback and downloads exclusively through the authenticated REST endpoint with `download=1` parameter support.
* **Cryptographic HMAC-SHA256 Streaming Tokens (SEC-01 & DOC-01)**: Implemented time-bounded, cryptographically signed streaming tokens (`hash_hmac('sha256', ...)`) with 7-day default TTL (`nikvotia_audio_token_ttl`), per-ticket salt rotation for instant revocation, and automated playback blocking on closed tickets.
* **HTTP Range Seeking**: Added full support for HTTP 206 Partial Content range requests (`Accept-Ranges: bytes`) for smooth audio seeking in browser players.
* **Privacy & Data Retention**: Added configurable audio retention window (PIPEDA Limiting Retention Principle 4.5 & GDPR Storage Limitation) with daily automated WP Cron pruning (`nikvotia_daily_data_retention_cleanup`).
* **Privacy Exporter & Eraser**: Integrated native WordPress Core Privacy Tools (`Tools > Export Personal Data` and `Tools > Erase Personal Data`) for full customer right-to-be-forgotten and data portability compliance.
* **Security Hardening**: Enforced cross-platform path normalization (`wp_normalize_path` and case-insensitive containment verification) and `X-Robots-Tag: noindex, nofollow, noarchive` on audio stream responses.
* **Consent Controls**: Added frontend AI voice processing consent notice on the floating recording dock with customizable admin notice text.
* **Telemetry Privacy**: Added site administrator email exclusion toggle ensuring zero personal identifiers in diagnostic telemetry.
* **Automated Testing**: Built and verified comprehensive 42-check regression test suite on WordPress 7.1.3 and PHP 8.2+.

### 1.3.6
* **UI & Accessibility**: Redesigned the ticket submission confirmation modal with ultra-high contrast styling for dark and light modes.
* **Feature**: Added automatic theme detection and an interactive Dark/Light mode switcher toggle right inside the success confirmation modal.
* **Hardening**: Applied strict CSS specificity and `!important` property isolation across modal typography and notices, preventing WordPress theme stylesheet text bleed.

### 1.3.5
* **Feature**: Added seamless manual API Key ID pairing for multi-site deployments and client licensing.
* **Fix**: Prevented random key regeneration overwrite when using pre-issued or manual API keys.
* **Fix**: Removed mandatory HMAC signature requirement for standalone API keys, resolving 401 Unauthorized rejections.
* **Reliability**: Enhanced connection auto-detection and BYOK settings interface.

### 1.3.4
* **Feature**: Added 1-Click "Test Gateway Health" diagnostic button in Settings under Nik Voice Cloud.
* **Diagnostics**: Added real-time detection and logging for Nik Voice Cloud gateway demo mode vs live Groq Whisper-v3 mode.
* **UI Transparency**: Added demo fallback indicator badge in Admin Ticket Console and All Tickets list table when gateway runs in demo fallback mode.
* **Security & Reliability**: Gateway health check probes `/v1/health` with PIPEDA compliance verification and auto-logging.

### 1.3.3
* **Fix**: Refined audio container security scanning to eliminate false-positive "File content failed security verification" rejections caused by compressed Opus bitstream entropy.
* **Feature**: Added dedicated System & AI Gateway Activity Logs console under VoiceDesk Settings (`tab=logs`) with event filtering, live status monitoring, and 1-click text log export.
* **Reliability**: Added end-to-end event logging across ticket submission, audio validation, and gateway API communication with WordPress debug log integration.

### 1.3.2
* **Strict Single-Provider Isolation**: Incoming voice tickets strictly use the selected active AI provider (Nik Voice Cloud, OpenAI, or Modulate.ai). Automatic cross-provider fallback and unintended secondary API calls are completely disabled.
* **AI Engine & Pipeline Audit Log**: Added comprehensive audit tracking displaying the exact active AI engine, STT provider, and triage model used for each ticket in the Admin Ticket Console and All Tickets list table.
* **Sovereign Cloud Triage**: Integrated sovereign triage and smart categorization for Nik Voice Cloud tickets with zero third-party data leakage.
* **UI Status Transparency**: Added clear Active/Dormant visual badges in VoiceDesk Settings indicating active single-engine mode and dormant API keys.

### 1.3.1
* **Fix**: Fixed 404 error when navigating to WooCommerce `/my-account/voicedesk-tickets/` endpoint.
* **Feature**: Added self-healing rewrite rules and fallback template router for custom customer portal endpoints.
* **Security**: Enhanced guest ticket security: guests are required to sign in to access voice recordings and AI summaries, protecting customer privacy.
* **Integration**: Added automatic email reconciliation linking guest-submitted voice tickets to customer accounts upon login.
* **UI**: Added prominent ticket notice banner on WooCommerce customer login form.

### 1.3.0
* **Feature**: Added 1-Click Connect to Nik Voice Cloud (Groq Whisper-v3) with zero local disk retention.
* **Security**: Added automatic cryptographic handshake and domain-bound API key provisioning.
* **Compliance**: Added Canadian PIPEDA compliance and zero-disk streaming gateway support.

### 1.2.5
* **Critical Security**: Resolved unauthenticated arbitrary file upload vulnerability in the public REST voice submission endpoint (props pnn219).
* **Hardening**: Added strict MIME verification, magic bytes validation, randomized filenames, and directory execution prevention.

### 1.2.4
* **Fix**: Removed unclosed `ob_start()` and buffer manipulation in REST API controller to adhere strictly to WordPress buffer lifecycle guidelines.
* **Reliability**: Re-verified clean pairing and zero dangling output buffers across all plugin hooks.

### 1.2.3
* **Compatibility**: Added forward-compatibility support for WordPress 7.0+ core AI Client (`wp_ai_client_prompt()`).
* **Standards**: Added `phpcs:ignore` annotations for direct AI provider integrations on WordPress < 7.0.
* **Compliance**: Re-verified zero errors and warnings across all WordPress Plugin Check sniffs.

### 1.2.2
* **Compliance**: Removed forbidden `move_uploaded_file()` function; adopted core `wp_upload_bits()` for WordPress.org compliance.
* **PHP 8.4**: Resolved PHP 8.4 deprecation warning in formatting functions by ensuring parameter null-safety.
* **i18n**: Fixed misplaced translators comment in REST API to comply with WordPress.org i18n standards.
* **WooCommerce**: Escaped WooCommerce endpoint output with `wp_kses_post()` in user portal.
* **Shortcodes**: Added backward-compatible shortcode alias `[nik_voicedesk_tickets]`.
* **Telemetry**: Replaced unverified `$_GET` parameters in telemetry with WordPress option update hooks.
* **Optimization**: Removed discouraged `load_plugin_textdomain()` call per WordPress 4.6+ guidelines.

### 1.2.1
* **Fix**: Fixed audio upload handling to explicitly allow `audio/webm`, `audio/ogg`, and `audio/wav` MIME types.
* **UI**: Replaced native browser alert popups with a modern, accessible in-page notification toast component.
* **Reliability**: Added fallback audio storage to `uploads/nikvotia/` to ensure reliability on strict hosts.
* **Resilience**: Hardened external AI and email delivery in try/catch blocks with reduced timeouts.
* **Cache**: Added automatic nonce retry to prevent 403 authorization rejections on cached pages.

### 1.2.0
* **Feature**: Added Modulate.ai Velma-2 Speech-to-Text API support.
* **UX**: Added reply deduplication and Post-Redirect-Get pattern to prevent duplicate emails on refresh.
* **Dark Mode**: Added Dark/Light Mode toggle for Ticket Console, Settings, and Ticket listing pages.
* **Settings**: Added developer attribution opt-in setting in Appearance tab.
* **Branding**: Updated corporate info to Nik Neural AI Inc. with light and dark mode favicon icons.
* **About Page**: Added About Nik Neural AI Inc. page under VoiceDesk Tickets.
* **Contrast**: Fixed contrast in light mode for dropdowns, labels, and action buttons.

### 1.1.0
* **WooCommerce**: Added WooCommerce My Account endpoint and customer dashboard integration.
* **UX**: Added interactive click tracking and visual pin drop during recording.
* **Ticketing**: Added unique Ticket ID generation and automatic confirmation emails.

### 1.0.0
* Initial release of Nik VoiceDesk AI.

## Security Disclosures & Acknowledgments
- **v1.2.5 Fix**: Unauthenticated Arbitrary File Upload / Remote Code Execution mitigation.
- **Responsible Reporter**: Special thanks to **pnn219** for identifying this vulnerability and adhering to coordinated vulnerability disclosure protocols.

---

## License

Nik Voice Ticketing AI is licensed under the GNU General Public License v2 or later. See [LICENSE](LICENSE) for details.\n