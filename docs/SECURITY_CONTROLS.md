# Security Controls Specification
# Nik Voice Ticketing AI v1.4.1

**Document Reference:** SEC-SPEC-v1.4.1  
**Scope:** Architecture, access enforcement, token cryptography, upload validation, and attack mitigations.  
**Audience:** Security Auditors, System Administrators, WordPress Platform Engineers.

---

## 1. Threat Model & Security Boundaries

Nik Voice Ticketing AI manages voice recordings, automated transcripts, sentiment analysis metadata, and administrative diagnostic telemetry. The threat boundary encompasses three primary interaction surfaces:

1. **Frontend Ticket Submission Surface (Public / Authenticated)**:
   - Voice recordings captured via Web Audio API / MediaRecorder in browser.
   - Multipart audio payloads submitted to WP REST API endpoints.
   - Attack vectors: Unauthenticated file upload, spoofed MIME types, web shell planting, audio token brute forcing, cross-user ticket inspection.

2. **Storage Subsystem (Local Filesystem)**:
   - Audio files stored on disk within `wp-content/uploads/nikvotia/`.
   - Threat vectors: Direct unauthenticated HTTP traversal, directory indexing, script execution via server handler overrides, Symlink traversal.

3. **Administrative & Diagnostic Console (WP Admin)**:
   - Audio streaming console, transcription review, external API synchronization, telemetry controls.
   - Threat vectors: Cross-Site Request Forgery (CSRF), Cross-Site Scripting (XSS), privilege escalation, insecure direct object reference (IDOR).

```
+-----------------------------------------------------------------------------------+
|                                  THREAT MODEL                                     |
+-----------------------------------------------------------------------------------+
|  [ Browser Client ]                                                               |
|        |                                                                          |
|        | (HTTPS POST /wp-json/nikvotia/v1/tickets)                                |
|        v                                                                          |
|  [ REST API Layer ] -------------------+                                          |
|        | (Non-executable Upload Check) | (HMAC-SHA256 Token Generation)           |
|        v                               v                                          |
|  [ In-Memory Byte Inspection ]   [ Token Store / Transients ]                     |
|    - Magic bytes check (EBML/RIFF)     - 1-hour expiration                        |
|    - Script signature scan             - Bound to ticket_id + user_id             |
|        |                               - Revocable on status change/deletion      |
|        v                               |                                          |
|  [ Storage Layer: .htaccess ]          v                                          |
|    - Deny from all (audio/scripts)  [ Audio Streaming Controller ]                |
|    - Script execution disabled         - Strict HTTP Range byte streaming         |
|    - Options -Indexes                  - Capability & Author access checks        |
+-----------------------------------------------------------------------------------+
```

---

## 2. In-Depth Audio Upload Validation & Sanitization

All incoming audio payloads undergo rigorous multi-stage validation prior to disk persistence:

### 2.1 File Extension Whitelisting
Only strictly recognized, non-executable audio extensions are accepted:
- `webm` (WebM audio / Opus)
- `ogg`, `oga` (Ogg audio / Vorbis / Opus)
- `wav` (Waveform audio)
- `mp3` (MPEG audio Layer 3)
- `m4a` (MPEG-4 audio)

Any file with double extensions (e.g., `ticket.php.webm`, `payload.phtml.wav`) or null byte injections (`%00`) is immediately rejected with HTTP 400 Bad Request.

### 2.2 Binary Header Magic Bytes Verification
MIME types supplied in `Content-Type` headers are untrusted. Nik Voice Ticketing AI directly reads the opening byte sequence of the uploaded stream:
- **WebM / MKV**: Validates the EBML header ID `0x1A 0x45 0xDF 0xA3` at offset 0.
- **WAV**: Validates ASCII `RIFF` at offset 0 and `WAVE` at offset 8.
- **Ogg**: Validates ASCII `OggS` at offset 0 (`0x4F 0x67 0x67 0x53`).
- **MP3**: Validates MPEG frame sync `0xFF 0xFB`, `0xFF 0xF3`, `0xFF 0xF2`, or ID3v2 tag header `0x49 0x44 0x33` (`ID3`).
- **M4A / MP4**: Validates `ftyp` box marker at offset 4 (`0x66 0x74 0x79 0x70`).

### 2.3 Bitstream Anti-Script Scanning
Audio files could potentially be polyglot files embedding server-executable scripts. Before persisting to storage, the entire byte buffer is scanned for malicious code indicators:
- PHP opening sequences: `<?php`, `<?=`, `<script language="php">`.
- Eval and execution signatures: `eval(`, `base64_decode(`, `system(`, `shell_exec(`, `passthru(`.
- Embedded HTML/JavaScript tags: `<script`, `<svg/onload`, `<iframe`.

If any signature is encountered, the file is rejected, discarded from memory, and an administrative security event is logged.

---

## 3. Storage Layer Protection (`.htaccess` & Server Directives)

Voice recordings are stored under `wp-content/uploads/nikvotia/`. To prevent unauthorized direct retrieval, the plugin enforces filesystem-level access controls.

### 3.1 Idempotent Apache `.htaccess` Protection Block
The plugin automatically generates and maintains a managed block inside `wp-content/uploads/nikvotia/.htaccess`:

```apache
# BEGIN NikVoiceTicketingAI Protection
# Deny direct HTTP access to voice recordings and prevent script execution
<FilesMatch "\.(webm|ogg|oga|wav|mp3|m4a|php|phtml|php3|php4|php5|php7|php8|phps|cgi|pl|exe|sh|py)$">
    <IfModule mod_authz_core.c>
        Require all denied
    </IfModule>
    <IfModule !mod_authz_core.c>
        Order deny,allow
        Deny from all
    </IfModule>
</FilesMatch>

# Prevent directory browsing
Options -Indexes

# Disable script execution engines if module is loaded
<IfModule mod_php.c>
    php_flag engine off
</IfModule>
<IfModule mod_php7.c>
    php_flag engine off
</IfModule>
<IfModule mod_php8.c>
    php_flag engine off
</IfModule>
# END NikVoiceTicketingAI Protection
```

### 3.2 Idempotence & Safe Coexistence
- The logic parses existing rules using marker tags `# BEGIN NikVoiceTicketingAI Protection` and `# END NikVoiceTicketingAI Protection`.
- Administrator-defined directives outside this block (such as custom WAF rules or cache controls) are preserved completely without corruption.
- Periodic health checks verify the existence and integrity of this block.

### 3.3 Non-Apache Environments (Nginx, IIS, Caddy)
Because Nginx and IIS ignore `.htaccess`, administrators running non-Apache web servers must apply equivalent configurations:

- **Nginx configuration**:
  ```nginx
  location ^~ /wp-content/uploads/nikvotia/ {
      deny all;
      return 403;
  }
  ```
- **IIS `web.config`**:
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

### 3.4 Filesystem Encryption Clarification
Web server `.htaccess` directives and file permissions implement network-layer access controls. Physical audio files stored on the server filesystem are unencrypted at the application layer. Environments requiring encryption at rest must configure operating-system level or storage-volume level encryption (e.g., Linux LUKS, Windows BitLocker, or managed cloud volume encryption).

---

## 4. Cryptographic HMAC-SHA256 Token Architecture

Because direct file URLs are denied by the web server, authorized playback and downloads occur strictly through an authenticated proxy endpoint:  
`GET /wp-json/nikvotia/v1/audio/{ticket_id}?token={token}`

### 4.1 Token Derivation & Claims
Tokens are derived using `hash_hmac` with the SHA-256 algorithm and the WordPress installation secret key (`SECURE_AUTH_KEY` or `AUTH_KEY`):

$$\text{Signature} = \text{HMAC-SHA256}\Big(\text{ticket\_id} \parallel \text{user\_id} \parallel \text{expiration\_ts} \parallel \text{action}, \; K_{\text{auth}}\Big)$$

Token components:
- `ticket_id`: Target ticket post ID.
- `user_id`: Target user ID (or guest session identifier `guest_<hash>`).
- `expiration`: Unix timestamp (strictly limited to **1 hour (3600 seconds)**).
- `token_id`: Unique random entropy string for stateful revocation.

### 4.2 Security Properties
1. **Time-Limited TTL**: Tokens automatically expire after 60 minutes.
2. **Post-Binding**: A valid token generated for Ticket #101 cannot be reused for Ticket #102. Attempted cross-ticket reuse returns HTTP 403.
3. **Identity-Binding**: Tokens issued to User A cannot be shared with or used by User B.
4. **Instant Revocation**:
   - Tickets marked "Resolved", "Closed", or moved to Trash automatically invalidate associated active audio tokens.
   - Deleting a ticket instantly purges all token transients from the database.
   - Administrators can trigger full token rotation from the Settings > Logs console.
5. **Legacy Token Invalidation**: Unsigned static bearer tokens are strictly rejected over HTTP streaming. Authorized administrators and authenticated ticket owners automatically trigger dynamic server-side token renewal (`Nikvotia_API::get_authenticated_stream_url()`) upon accessing ticket records without exposing a public unauthenticated refresh endpoint.

---

## 5. Streaming Proxy & Range Request Engine

Serving multi-megabyte audio files requires low latency without exhausting server RAM or leaking file paths.

### 5.1 Byte-Range Delivery (`Accept-Ranges: bytes`)
- The streaming controller inspects the HTTP `Range` request header (e.g., `bytes=0-1048575`).
- Valid ranges respond with `HTTP 206 Partial Content`, `Content-Range: bytes START-END/TOTAL`, and `Content-Length`.
- Streaming uses small, buffered chunks (64 KB) via `fpassthru` or `fread` within an unbuffered output loop, preventing PHP memory exhaustion (`memory_limit` breaches).

### 5.2 Security Headers Enforced During Playback
Whenever audio is proxied, the response enforces defensive headers:
- `Content-Type: audio/webm` (or matching validated MIME type; never `text/html` or generic `application/octet-stream`).
- `X-Content-Type-Options: nosniff`: Prevents MIME-confusion attacks in Chromium and WebKit.
- `Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0`: Prevents intermediate proxy/CDN caching of sensitive recordings.
- `Content-Disposition: inline`: Audio plays natively in `<audio>` tags rather than triggering arbitrary downloads.

---

## 6. Access Control & Capability Matrix

Every admin screen, REST endpoint, and audio stream validates caller authorization against granular WordPress capabilities.

| Action / Endpoint | Capability Required | Guest Access | IDOR Protection |
| :--- | :--- | :--- | :--- |
| **Submit Ticket via REST** | `None` (Public / Guest) or `read` | Yes (if enabled in settings) | Nonce verification; rate limit enforced |
| **View Ticket in Admin Console** | `edit_voicedesk_tickets` or `edit_posts` | No (HTTP 401/403) | User role check; sanitized post query |
| **Stream Audio via Proxy** | `edit_voicedesk_tickets` OR Author of ticket | Yes (Token with valid guest session) | HMAC-SHA256 signature match + expiration check |
| **Manage Plugin Settings** | `manage_options` | No | `current_user_can('manage_options')` + WP Nonce |
| **Clear Audit Logs** | `manage_options` | No | Form nonce check + re-authentication |
| **Trigger AI Resync** | `manage_options` or `edit_voicedesk_tickets` | No | REST Nonce check (`X-WP-Nonce`) |
| **Telemetry Opt-In / Out** | `manage_options` | No | Settings API sanitization + permission gate |

---

## 7. CSRF & Cross-Site Scripting (XSS) Defenses

### 7.1 Cross-Site Request Forgery (CSRF)
- All administrative POST actions (saving API keys, updating data retention periods, clearing logs, toggling telemetry) require valid nonces via `check_admin_referer('nikvotia_settings_nonce')` or `wp_verify_nonce()`.
- REST API mutations require `X-WP-Nonce` header verification matching the current logged-in user session.

### 7.2 Output Sanitization & Context-Aware Escaping
All rendered data is escaped at the point of output:
- URLs: `esc_url()`
- HTML Attributes: `esc_attr()`
- Body Text: `esc_html__()` with explicit translator context comments.
- Rich-Text Post Summaries: `wp_kses_post()`.
- JSON REST Responses: Sanitized via `rest_sanitize_value_from_schema()` before JSON encoding.

---

## 8. Summary of Security Verification Status

| Vulnerability ID | Vulnerability Description | Mitigation Status in v1.4.0 |
| :--- | :--- | :--- |
| **FINDING-SEC-01** | Audio tokens lacked expiration and revocation. | **RESOLVED**: 1-hour HMAC-SHA256 tokens with transient revocation on status changes. |
| **FINDING-SEC-02** | Direct HTTP access to physical audio files. | **RESOLVED**: Managed `.htaccess` block blocks all direct requests (HTTP 403) across all audio extensions. |
| **FINDING-SEC-03** | Weak `.htaccess` update logic bypassed on substring match. | **RESOLVED**: Marker-based idempotent block rewrite logic protects all audio types and scripts. |
| **FINDING-SEC-04** | Direct physical upload URLs exposed in HTML source. | **RESOLVED**: All physical URLs replaced with authenticated streaming proxy URLs. |
| **FINDING-SEC-05** | Lack of byte-range streaming for large recordings. | **RESOLVED**: Native HTTP 206 partial content streaming engine with 64KB memory chunking. |
