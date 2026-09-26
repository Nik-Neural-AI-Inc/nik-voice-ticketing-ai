=== Nik VoiceDesk AI ===
Contributors: nikneural, aboozar
Donate link: https://nikneural.ca/
Tags: voice, ai, ticketing, helpdesk, audio
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Zero-friction, AI-powered voice ticketing system for WordPress with Whisper & Modulate.ai speech-to-text.

== Description ==

Nik VoiceDesk AI is a production-ready, zero-friction, AI-powered voice ticketing system for WordPress developed by Nik Neural AI Inc. It allows website visitors and logged-in customers to record voice messages, automatically transcribe them using advanced Speech-to-Text models (OpenAI Whisper or Modulate.ai), intelligently categorize and summarize the issue using Large Language Models (OpenAI GPT-4o-mini), and track click interactions on the page during the recording session.

= Core Features =

* **Vanilla JS Floating Mic Button**: Lightweight, ultra-responsive microphone trigger positioned cleanly on the screen.
* **Modern Recording Dock**: Real-time recording indicator with pulsating red dot, live elapsed timer, and dynamic animated audio waveform.
* **Interactive Click Tracking & Visual Pinning**: Captures DOM element clicks, CSS selectors, coordinates, and millisecond time offsets during voice recording.
* **Speech-to-Text Multi-Model Support**: Bring Your Own Key (BYOK) integration with OpenAI Whisper (`whisper-1`) or Modulate.ai Velma-2.
* **Automated AI Summary & Triage**: Uses LLMs to generate a concise summary and intelligently categorize issues into departments (Sales, Technical Support, Billing, General Support).
* **Unique Ticket ID & Email Delivery**: Generates a unique Ticket ID and sends an email confirmation directly to the user.
* **Comprehensive Admin Console**: Clean 2-column layout with dark mode/light mode switcher, audio playback, download link, click timeline, and deduplicated reply thread.
* **Customer Ticket Portal & WooCommerce Integration**: Embed ticket portal via `[nik_voicedesk_tickets]` shortcode or access via WooCommerce "My Account" dashboard endpoint.
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
* Terms of Service: https://modulate.ai/terms
* Privacy Policy: https://modulate.ai/privacy

3. **Nik Neural AI Telemetry** (Optional - Explicit Opt-In Only)
* Purpose: Anonymous diagnostic telemetry (WordPress version, PHP version, server environment, and site URL) to improve plugin compatibility.
* Data Sent: Site URL, administrator email (optional), PHP version, WordPress version, server software.
* Service URL: https://nikneural.ca/tracking/
* Privacy Policy: https://nikneural.ca/

4. **Nik Neural Enterprise Licensing & Stripe** (Optional - For Pro Licensees)
* Purpose: Remote validation of optional Enterprise Pro licenses (to remove administrative ad banners and white-label the plugin) and Stripe checkout processing.
* Data Sent: License key, requesting website domain/URL.
* Service URL: https://nikneural.ca/tracking/ and https://buy.stripe.com/
* Terms of Service: https://nikneural.ca/
* Stripe Privacy Policy: https://stripe.com/privacy

== Installation ==

1. Upload the `nik-voicedesk` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Navigate to **VoiceDesk Tickets > Settings** to configure your API keys (OpenAI or Modulate.ai) and portal page.
4. (Optional) Add the `[nik_voicedesk_tickets]` shortcode to a WordPress page for your customer support portal.

== Frequently Asked Questions ==

= Does this plugin require external subscriptions? =
No. The plugin operates on a Bring Your Own Key (BYOK) model. You only pay your respective AI provider (OpenAI or Modulate.ai) directly for the API tokens you consume.

= Are third-party frontend libraries like jQuery or React loaded? =
No. The entire frontend recording dock, modal, click tracking, and audio processing are written in pure Vanilla JavaScript (0 dependencies).

= Are voice recordings stored on my WordPress site? =
Yes. Voice recordings are saved in your WordPress uploads directory and streamed via authenticated REST endpoints.

= Is the developer attribution link optional? =
Yes. Per WordPress.org guidelines, the "Powered by Nik Neural AI Inc." badge is completely optional and can be toggled on or off under VoiceDesk Settings > Appearance.

== Screenshots ==

1. Floating microphone button and modern recording dock on frontend.
2. Unified Admin Ticket Console with audio player, click timeline, and deduplicated reply thread.
3. Customer Ticket Portal with status tracking and reply thread.
4. VoiceDesk Settings page with Dark Mode toggle and AI configuration.

== Changelog ==

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

= 1.2.0 =
Update to version 1.2.0 for Modulate.ai integration, Dark Mode support, and reply deduplication.
