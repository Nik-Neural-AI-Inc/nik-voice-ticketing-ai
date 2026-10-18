=== Nik Voice Ticketing AI ===
Contributors: nikneuralaiinc, aboozaresmaili
Donate link: https://nikneural.ca/
Tags: voice, ai, ticketing, helpdesk, audio
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.4.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Zero-friction, AI-powered voice ticketing system for WordPress with Nik Voice Cloud, Whisper & Modulate.ai speech-to-text.

== Description ==

Nik Voice Ticketing AI is a production-ready, zero-friction, AI-powered voice ticketing system for WordPress developed by Nik Neural AI Inc. It allows website visitors and logged-in customers to record voice messages, automatically transcribe them using advanced Speech-to-Text models (Nik Voice Cloud, OpenAI Whisper, or Modulate.ai), intelligently categorize and summarize the issue using Large Language Models (OpenAI GPT-4o-mini or Nik Sovereign AI), and track click interactions on the page during the recording session.

= Core Features =

* **1-Click Nik Voice Cloud**: Sovereign Canadian Cloud integration with sub-second Groq Whisper-v3 TPU acceleration, ephemeral cloud inference, and sovereign local triage fallback.
* **BYOK Multi-Model STT Support**: Bring Your Own Key integration with OpenAI Whisper (`whisper-1`) or Modulate.ai Velma-2.
* **Strict Single-Provider Isolation**: Enforces single-engine mode across transcription and triage with zero unintended secondary API calls or data leakage.
* **Vanilla JS Floating Mic Button**: Lightweight, ultra-responsive microphone trigger positioned cleanly on the screen (0 dependencies).
* **Modern Recording Dock**: Real-time recording indicator with pulsating red dot, live elapsed timer, and dynamic animated audio waveform.
* **Interactive Click Tracking & Visual Pinning**: Captures DOM element clicks, CSS selectors, coordinates, and millisecond time offsets during voice recording with visual ripple pins.
* **Automated AI Summary & Triage**: Uses LLMs to generate concise summaries, urgency classification, sentiment scores, and intelligently categorize issues into departments (Sales, Technical Support, Billing, General Support).
* **Hardened Audio Storage & Authenticated Streaming**: Direct `.htaccess` upload protection blocking all audio formats (`webm`, `ogg`, `oga`, `wav`, `mp3`, `m4a`) and scripts; time-bounded HMAC-SHA256 tokens (1-hour TTL); dynamic server-side token refresh; strict anti-caching headers (`Cache-Control: private, no-cache, no-store, must-revalidate, max-age=0`); HTTP 206 partial content byte-range seeking; attachment downloads (`?download=1`).
* **Customer Ticket Portal & Self-Healing Navigation**: Dedicated portal via shortcode `[nikvotia_tickets]`, auto-provisioning of default portal page when WooCommerce is inactive, seamless WooCommerce "My Account" integration, context-aware login routing, and automatic guest ticket reconciliation.
* **Comprehensive Admin Ticket Console**: Clean 2-column layout with dark mode/light mode switcher, audio playback, download link, click timeline, deduplicated staff replies with Post-Redirect-Get pattern, and sanitized client environment (OS/Browser).
* **Enterprise Privacy & Data Retention**: Configurable audio retention window (0 to 365 days, or indefinite) with scheduled daily WP Cron pruning; native WordPress Core Privacy Tools (`Tools > Export Personal Data` and `Tools > Erase Personal Data`); frontend consent notice; defensive credential redaction in logger and exports.
* **System & AI Activity Logs**: Dedicated audit console (`tab=logs`) with severity filtering, live status monitoring, 1-click text log export, and automatic credential sanitization.
* **Advanced Visibility Controls**: Target specific post types, pages, user login states, or specific User IDs.
* **Zero 3rd-Party Frontend Dependencies**: Built entirely with pure Vanilla JavaScript and CSS. No jQuery, React, or bulky frameworks.

== External Services ==

This plugin connects to external third-party APIs to process voice audio recordings and deliver AI capabilities:

1. **OpenAI API**
* Purpose: Audio transcription via Whisper (`whisper-1`) and automated ticket summarization/triage via GPT-4o-mini.
* Data Sent: Voice audio file for speech transcription; transcribed ticket text for summarization.
* Service URL: https://api.openai.com
* Terms of Service: https://openai.com/policies/terms-of-use
* Privacy Policy: https://openai.com/policies/privacy-policy

2. **Modulate.ai API**
* Purpose: Fast multilingual speech-to-text transcription and voice intelligence via Velma-2.
* Data Sent: Voice audio recording file.
* Service URL: https://api.modulate.ai
* Terms of Service: https://www.modulate.ai/terms-and-conditions
* Privacy Policy: https://www.modulate.ai/privacy-policy

3. **Nik Neural AI Telemetry** (Optional - Explicit Opt-In Only)
* Purpose: Diagnostic telemetry (WordPress version, PHP version, server environment, and pseudonymous site identifier) to improve plugin compatibility.
* Data Sent: Pseudonymous site hash (SHA-256 of home URL), administrator email (optional, disabled by default), PHP version, WordPress version, server software, plugin version, ticket count, and timestamp.
* Service URL: https://nikneural.ca/tracking/
* Privacy Policy: https://nikneural.ca/

4. **Nik Voice Cloud & Canadian Sovereign AI** (Optional)
* Purpose: Canadian speech-to-text (Groq Whisper-v3) and in-flight ephemeral cloud inference with Canadian data residency options.
* Service URL: https://voice.nikneural.ca
* Product Overview: https://nikneural.ca/voicedesk-ai/
* Terms of Service: https://nikneural.ca/
* Privacy Policy: https://nikneural.ca/

= Official Links & Resources =

* Official Website: https://nikneural.ca/
* Product Page: https://nikneural.ca/voicedesk-ai/
* WordPress.org Plugin: https://wordpress.org/plugins/nik-voice-ticketing-ai/
* GitHub Repository: https://github.com/Nik-Neural-AI-Inc/nik-voice-ticketing-ai

== Installation ==

1. Upload the `nik-voice-ticketing-ai` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Navigate to **VoiceDesk Tickets > Settings** to connect to Nik Voice Cloud with 1-click or configure your BYOK API keys (OpenAI or Modulate.ai), customize retention rules, and select your customer portal page.
4. (Optional) Add the `[nikvotia_tickets]` shortcode to a WordPress page for your customer support portal.

== Frequently Asked Questions ==

= Does this plugin require external subscriptions? =
No. You can connect to Nik Voice Cloud with 1-click or operate on a Bring Your Own Key (BYOK) model with OpenAI or Modulate.ai, paying your chosen provider directly for consumed tokens.

= Are third-party frontend libraries like jQuery or React loaded? =
No. The entire frontend recording dock, modal, click tracking, and audio processing are written in pure Vanilla JavaScript (0 dependencies).

= Are voice recordings stored on my WordPress site? =
Yes. Voice recordings are stored in `wp-content/uploads/nikvotia/` with direct webserver HTTP access denied via `.htaccess`. Playback and downloads are securely routed through authenticated REST streaming endpoints (`/wp-json/nikvotia/v1/audio/{ticket_id}` and `/wp-json/nik-voice/v1/audio/{ticket_id}`) using time-bounded cryptographic HMAC-SHA256 tokens (1-hour TTL with authenticated URL refresh) and WordPress user capabilities.

= Is the developer attribution link optional? =
Yes. Per WordPress.org guidelines, the "Powered by Nik Neural AI Inc." badge is completely optional and can be toggled on or off under VoiceDesk Settings > Appearance.

== Screenshots ==

1. Floating microphone button and modern recording dock on frontend.
2. Unified Admin Ticket Console with audio player, click timeline, and deduplicated reply thread.
3. Customer Ticket Portal with status tracking and reply thread.
4. VoiceDesk Settings page with Dark Mode toggle and AI configuration.

== Changelog ==

= 1.4.1 =
* Security & Token Hardening: Replaced static legacy bearer token fallback with strict rejection of unauthenticated static tokens, eliminating indefinite token validity.
* Token Lifetime Standardization: Formally standardized on an explicit 1-hour (3600 seconds) token TTL across all code, API endpoints, tests, and documentation.
* Dynamic Stream URL Refresh: Implemented server-side authenticated URL refresh (`Nikvotia_API::get_authenticated_stream_url()`) ensuring authorized administrators and authenticated ticket owners always receive valid, time-bounded streaming tokens upon accessing ticket views.
* Zero Public Refresh Exposure: Refresh logic executes strictly inside authenticated server-side render contexts without exposing any unauthenticated public token refresh endpoints.
* Credential Protection & Redaction: Completely removed gateway authentication credentials (`key_id`) from cloud handshake and streaming logs. Implemented defensive recursive context sanitization in `Nikvotia_Logger` ensuring sensitive keys (`key_id`, `api_key`, `token`, `secret`, `bearer`, etc.) are permanently redacted in stored database logs, error logs, and exported diagnostic files.
* Private Audio Anti-Caching: Replaced the 1-year cache header on the audio streaming endpoint with strict anti-caching headers (`Cache-Control: private, no-cache, no-store, must-revalidate, max-age=0`, `Pragma: no-cache`, `Expires: 0`) to prevent browser caching of private voice recordings past the 1-hour token lifetime.
* Diagnostic Telemetry Realignment: Replaced raw site URL transmission with a pseudonymous SHA-256 site hash (`site_hash`), aligned the tracking endpoint to `https://nikneural.ca/tracking/`, and updated privacy disclosures and payload tests.
* Admin UI & Disclosure Polish: Removed demo server tips from production settings screens, updated architectural highlights to v1.4.1, and factually qualified cloud vs. local storage (clarifying that audio is stored locally on WordPress per configured retention schedule while cloud gateway operates ephemerally in-flight).
* Portal Asset Reliability: Guaranteed frontend stylesheet and script enqueuing across all custom portal pages, shortcodes, and WooCommerce account endpoints.
* Documentation & Regulatory Accuracy: Comprehensively audited all codebase strings and documentation to qualify data residency, TLS version negotiation, local unencrypted filesystem storage, and compliance assertions.
* Regression Verification: Expanded automated test suite with comprehensive assertions covering legacy token rejection, 1-hour TTL enforcement, authenticated stream URL dynamic refresh, credential redaction, anti-caching headers, and telemetry privacy controls with 100% pass rate.

= 1.4.0 =
* Security Remediation: Upgraded .htaccess upload protection with idempotent managed markers (# BEGIN NikVoiceTicketingAI Protection) to protect all audio extensions (webm, ogg, oga, wav, mp3, m4a) while preserving administrator rules.
* Security Hardening: Completely removed physical audio URLs from customer portal and admin HTML, routing 100% of audio playback through time-bounded HMAC-SHA256 authenticated streaming endpoints.
* UI/UX Polish: Removed WordPress metabox header bar and up/down handle arrows from Ticket Console on post.php edit screen.
* Dark Mode Overhaul: Resolved contrast and brightness issues on post.php (transcript, empty replies box, and audit items now use sleek dark backgrounds), and styled list table department badges.
* Settings & Logs Modernization: Rebuilt API Connected card with a clean, structured key-value table and upgraded Logs tab with responsive action buttons, status bar, and spacious padding.
* Pro Tab Alignment: Eliminated all references to telephony/VoIP marketing and aligned all feature matrix items to verified capabilities (Sovereign Canadian Cloud, dedicated Groq Whisper-v3 TPU, CRM webhooks, SLA support).
* Regulatory & Compliance Qualification: Qualified all regulatory and privacy references across documentation and UI to accurately describe technical capabilities and privacy controls.
* Extended Documentation: Added formal privacy and security references in docs/ (PRIVACY_DATA_FLOW.md, PRIVACY_GAPS.md, SECURITY_CONTROLS.md, THIRD_PARTY_SERVICE_REVIEW.md).
* Regression Test Suite: Expanded automated test suite with comprehensive assertions covering TEST-01 to TEST-30 with 100% pass rate.

= 1.3.9 =
* Plugin Check Compliance: Added WordPress-Coding-Standards (WPCS) annotations for direct binary HTTP 206 audio range streaming (fopen, fread, fclose) and Core Privacy Exporter/Eraser and pruning meta queries, resolving all Plugin Check notices.
* Admin UI Contrast Overhaul: Overhauled API Configuration tab (tab=api) and System & AI Activity Logs tab (tab=logs) with dedicated semantic CSS classes and ultra-high-contrast styling for both Dark and Light modes.
* Table Hardening: Expanded Category column on logs table with nowrap pill badges, eliminating awkward word-wrapping on long event identifiers (such as SECURITY_VERIFICATION).
* Pro & Enterprise Architecture: Modernized the Pro tab (tab=pro) with a comprehensive feature matrix comparing Community Edition vs. Sovereign Canadian Cloud, dedicated AI processing, and enterprise CRM/ERP webhooks.
* Corporate Records & About Page: Updated About page (nikvotia-about) with verified corporate information, official plugin links, two verified contributors (nikneuralaiinc, aboozaresmaili), and v1.3.9 architectural highlights.
* Smart Theming: Added automatic OS (prefers-color-scheme) and admin theme dark mode auto-detection to admin.js to prevent theme flashing.

= 1.3.8 =
* Fix & Accessibility: Overhauled Customer Ticket Portal and Single Ticket View with ultra-high contrast styling in both Dark and Light modes, eliminating faint/invisible text caused by external theme stylesheet bleeding.
* Feature: Added smart automatic theme detection (system preference, theme dark classes, and body background luminance) and seamless Dark/Light mode switcher toggle for the single ticket detail view.
* Fix: Resolved modal "View Ticket" endless page reload bug by implementing automatic provisioning for the default portal page when WooCommerce is inactive.
* Security & Routing: Enforced authentication-aware routing in customer portal: unauthenticated guests are securely redirected to login with safe redirect_to parameter, and guest tickets auto-bind to customer accounts upon login.
* Packaging: Removed development/CI test suite folder from production distribution archive to deliver a lightweight, clean package for end users.

= 1.3.7 =
* Security: Blocked direct HTTP access to audio recordings in `wp-content/uploads/nikvotia/` via automated Apache `.htaccess` directives and added Nginx/IIS/Caddy configuration guidelines.
* Security: Fully eliminated direct physical audio URLs from rendered admin console and frontend portal HTML templates.
* Security: Implemented time-bounded, cryptographically signed HMAC-SHA256 streaming tokens with configurable TTL, salt rotation revocation, and automatic playback disabling for closed tickets.
* Feature: Added HTTP 206 Partial Content Range streaming support for smooth audio seeking in browser audio players.
* Privacy & Data Retention: Added configurable audio retention window (supporting storage limitation and retention management) with daily automated WP Cron pruning.
* Privacy Exporter & Eraser: Integrated native WordPress Core Privacy Tools (Tools > Export Personal Data and Tools > Erase Personal Data) to support customer data subject access and erasure requests.
* Security Hardening: Enforced cross-platform path normalization (wp_normalize_path and case-insensitive containment verification) and X-Robots-Tag: noindex, nofollow, noarchive on audio streams.
* Consent Controls: Added frontend AI voice processing consent notice on the floating recording dock.
* Telemetry Privacy: Added site administrator email exclusion toggle ensuring zero personal identifiers in diagnostic telemetry.
* Automated Testing: Built and verified comprehensive 42-check regression test suite on WordPress 7.1.3 and PHP 8.2+.

= 1.3.6 =
* UI & Accessibility: Redesigned the ticket submission confirmation modal with ultra-high contrast styling for dark and light modes.
* Feature: Added automatic theme detection and an interactive Dark/Light mode switcher toggle right inside the success confirmation modal.
* Hardening: Applied strict CSS specificity and !important property isolation across modal typography and notices, preventing WordPress theme stylesheet text bleed.

= 1.3.5 =
* Feature: Added seamless manual API Key ID pairing for multi-site deployments and client licensing.
* Fix: Prevented random key regeneration overwrite when using pre-issued or manual API keys.
* Fix: Removed mandatory HMAC signature requirement for standalone API keys, resolving 401 Unauthorized rejections.
* Reliability: Enhanced connection auto-detection and BYOK settings interface.

= 1.3.4 =
* Feature: Added 1-Click "Test Gateway Health" diagnostic button in Settings under Nik Voice Cloud.
* Diagnostics: Added real-time detection and logging for Nik Voice Cloud gateway demo mode vs live Groq Whisper-v3 mode.
* UI Transparency: Added demo fallback indicator badge in Admin Ticket Console and All Tickets list table when gateway runs in demo fallback mode.
* Security & Reliability: Gateway health check probes /v1/health with gateway status verification and auto-logging.

= 1.3.3 =
* Fix: Refined audio container security scanning to eliminate false-positive "File content failed security verification" rejections caused by compressed Opus bitstream entropy.
* Feature: Added dedicated System & AI Gateway Activity Logs console under VoiceDesk Settings (tab=logs) with event filtering, live status monitoring, and 1-click text log export.
* Reliability: Added end-to-end event logging across ticket submission, audio validation, and gateway API communication with WordPress debug log integration.

= 1.3.2 =
* Strict Single-Provider Isolation: Incoming voice tickets strictly use the selected active AI provider (Nik Voice Cloud, OpenAI, or Modulate.ai). Automatic cross-provider fallback and unintended secondary API calls are completely disabled.
* AI Engine & Pipeline Audit Log: Added comprehensive audit tracking displaying the exact active AI engine, STT provider, and triage model used for each ticket in the Admin Ticket Console and All Tickets list table.
* Sovereign Cloud Triage: Integrated sovereign triage and smart categorization for Nik Voice Cloud tickets with strict single-provider isolation.
* UI Status Transparency: Added clear Active/Dormant visual badges in VoiceDesk Settings indicating active single-engine mode and dormant API keys.

= 1.3.1 =
* Fixed 404 error when navigating to WooCommerce /my-account/voicedesk-tickets/ endpoint.
* Added self-healing rewrite rules and fallback template router for custom customer portal endpoints.
* Enhanced guest ticket security: guests are required to sign in to access voice recordings and AI summaries, protecting customer privacy.
* Added automatic email reconciliation linking guest-submitted voice tickets to customer accounts upon login.
* Added prominent ticket notice banner on WooCommerce customer login form.

= 1.3.0 =
* Added 1-Click Connect to Nik Voice Cloud (Groq Whisper-v3) with in-flight ephemeral cloud processing.
* Added automatic cryptographic handshake and domain-bound API key provisioning.
* Added Canadian data residency support and in-flight streaming gateway integration.

= 1.2.5 =
* Critical Security: Resolved unauthenticated arbitrary file upload vulnerability in the public REST voice submission endpoint (props pnn219).
* Hardening: Added strict MIME verification, magic bytes validation, randomized filenames, and directory execution prevention.

= 1.2.4 =
* Removed unclosed ob_start() and buffer manipulation in REST API controller to adhere strictly to WordPress buffer lifecycle guidelines.
* Re-verified clean pairing and zero dangling output buffers across all plugin hooks.

= 1.2.3 =
* Added forward-compatibility support for WordPress 7.0+ core AI Client (wp_ai_client_prompt()).
* Added phpcs:ignore annotations for direct AI provider integrations on WordPress < 7.0.
* Re-verified zero errors and warnings across all WordPress Plugin Check sniffs.

= 1.2.2 =
* Removed forbidden move_uploaded_file() function; adopted core wp_upload_bits() for WordPress.org compliance.
* Resolved PHP 8.4 deprecation warning in formatting functions by ensuring parameter null-safety.
* Fixed misplaced translators comment in REST API to comply with WordPress.org i18n standards.
* Escaped WooCommerce endpoint output with wp_kses_post() in user portal.
* Added backward-compatible shortcode alias [nik_voicedesk_tickets].
* Replaced unverified $_GET parameters in telemetry with WordPress option update hooks.
* Removed discouraged load_plugin_textdomain() call per WordPress 4.6+ guidelines.

= 1.2.1 =
* Fixed audio upload handling to explicitly allow audio/webm, audio/ogg, and audio/wav MIME types.
* Replaced native browser alert popups with a modern, accessible in-page notification toast component.
* Added fallback audio storage to uploads/nikvotia/ to ensure reliability on strict hosts.
* Hardened external AI and email delivery in try/catch blocks with reduced timeouts.
* Added automatic nonce retry to prevent 403 authorization rejections on cached pages.
* Updated standard Semantic Versioning roll across all plugin headers and assets.

= 1.2.0 =
* Added Modulate.ai Velma-2 Speech-to-Text API support.
* Added reply deduplication and Post-Redirect-Get pattern to prevent duplicate emails on refresh.
* Added Dark/Light Mode toggle for Ticket Console, Settings, and Ticket listing pages.
* Added developer attribution opt-in setting in Appearance tab.
* Updated corporate info to Nik Neural AI Inc. with light and dark mode favicon icons.
* Added About Nik Neural AI Inc. page under VoiceDesk Tickets.
* Fixed contrast in light mode for dropdowns, labels, and action buttons.

= 1.1.0 =
* Added WooCommerce My Account endpoint and customer dashboard integration.
* Added interactive click tracking and visual pin drop during recording.
* Added unique Ticket ID generation and automatic confirmation emails.

= 1.0.0 =
* Initial release of Nik VoiceDesk AI.

== Upgrade Notice ==

= 1.4.1 =
Essential update: Standardizes 1-hour token TTL with dynamic authenticated URL refresh, eliminates static token fallbacks, sanitizes credentials in logs, implements private audio anti-caching, aligns diagnostic telemetry, and polishes portal styles.

= 1.4.0 =
Security and maintenance release: Hardens .htaccess upload protection with idempotent markers, eliminates direct physical audio URLs, updates dark mode contrast, modernizes settings and logs tabs, and documents privacy safeguards.

= 1.3.6 =
Recommended update: Resolves low contrast and faint text on the ticket submission modal across dark and light themes, and adds an interactive modal theme switcher.

= 1.3.5 =
Recommended update: Enables standalone customer API keys without HMAC secrets and prevents automatic key overwrites on client sites.

= 1.3.4 =
Recommended update: Adds 1-Click Gateway Health Test diagnostics and transparent demo-mode detection for Nik Voice Cloud.

= 1.3.3 =
Recommended update: Fixes false-positive security check on audio recordings and adds a dedicated System & AI Activity Logs viewer in settings.

= 1.3.2 =
Recommended update: Enforces strict single-provider AI engine isolation, sovereign ticket triage for Nik Voice Cloud, and adds an admin AI pipeline audit log.

= 1.3.1 =
Recommended update: Fixes customer ticket portal 404 on WooCommerce /my-account/ endpoint and secures guest ticket views behind login.

= 1.3.0 =
Recommended update: 1-Click Connect to Nik Voice Cloud (Groq Whisper-v3) with in-flight ephemeral cloud processing.

= 1.2.5 =
Critical Security Update: Immediately update to resolve an unauthenticated file upload flaw. Discovered and responsibly disclosed by pnn219.

= 1.2.4 =
Recommended update: Eliminates unclosed output buffer, ensuring safe multi-plugin lifecycle compatibility.

= 1.2.3 =
Recommended update: Forward-compatibility with WordPress 7.0 AI Client, PCP annotations, and full compliance.

= 1.2.2 =
Recommended update: Full compliance with WordPress.org Automated Plugin Scanning, PHP 8.4 support, and user portal shortcode alias.

= 1.2.1 =
Recommended update: fixes audio upload MIME types, replaces browser alert popups with in-page notices, and prevents cache-related submission errors.
