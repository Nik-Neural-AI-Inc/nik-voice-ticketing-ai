# Nik Voice Ticketing AI

[![Version](https://img.shields.io/badge/version-1.2.2-blue.svg)](https://nikneural.ca/pl/voicedesk.php)
[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.svg)](LICENSE)
[![PIPEDA Compliant](https://img.shields.io/badge/Compliance-PIPEDA-green.svg)](https://nikneural.ca/)
[![Nik Neural AI Inc.](https://img.shields.io/badge/Developed%20by-Nik%20Neural%20AI%20Inc.-orange.svg)](https://nikneural.ca/)

**Nik Voice Ticketing AI** is a production-ready, zero-friction, AI-powered voice ticketing system for WordPress developed by **Nik Neural AI Inc.** It enables website visitors and logged-in customers to record voice messages, automatically transcribe them using advanced Speech-to-Text models (OpenAI Whisper or Modulate.ai), intelligently categorize and summarize the issue using Large Language Models, and track click interactions on the page during the recording session.

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

---

## Requirements

- **WordPress**: 5.8 or higher
- **PHP**: 7.4 or higher (fully tested on PHP 8.0, 8.1, and 8.2)
- **SSL Certificate**: HTTPS is required for modern browsers to grant microphone permissions (`getUserMedia`).
- **cURL Extension**: Enabled in PHP for outbound REST API requests.

---

## Installation & Setup

1. **Upload Plugin**:
   - Download the `nik-voicedesk` directory.
   - Upload it to your WordPress `/wp-content/plugins/` directory.
2. **Activate Plugin**:
   - Navigate to **Plugins** in the WordPress Admin and activate **Nik VoiceDesk AI**.
3. **Configure Settings**:
   - Navigate to **VoiceDesk Tickets > Settings** in the WordPress admin menu.
   - **General Tab**: Select your customer portal page and define button visibility rules.
   - **AI Integrations Tab**: Choose your STT model (Whisper or Modulate.ai) and enter your API keys.
   - **Appearance Tab**: Customize the button color, icon color, and tooltip text.
   - **License Tab**: Enter your Enterprise Pro license key to remove all branding.

---

## Shortcodes

### Customer Ticket Portal
```text
[nik_voicedesk_tickets]
```
Add this shortcode to any WordPress page (e.g., `/support/` or `/my-tickets/`) to provide users with a clean, responsive portal to track their tickets and reply to staff.

---

## Privacy & Compliance (PIPEDA by Default)

Nik VoiceDesk AI was designed by **Nik Neural AI Inc.** following the principles of Canadian privacy compliance (PIPEDA):
- No third-party SaaS middleware is used; all API requests go directly from your WordPress server to your chosen AI provider.
- User environment details are sanitized to only include high-level OS and Browser names, stripping hardware and browser fingerprinting data.
- Voice audio files are stored securely in WordPress uploads and streamed via authenticated REST endpoints.

---

## Corporate Information

**Nik Neural AI Inc.**  
112-970 Burrard Street, Office# 1760  
Vancouver, BC V6Z 2R4, Canada  
Phone: (604) 283-7353  
Official Website: [https://nikneural.ca/](https://nikneural.ca/)  
VoiceDesk: [https://nikneural.ca/pl/voicedesk.php](https://nikneural.ca/pl/voicedesk.php)

---

## License

Nik VoiceDesk AI is licensed under the GNU General Public License v2 or later. See [LICENSE](LICENSE) for details.\n