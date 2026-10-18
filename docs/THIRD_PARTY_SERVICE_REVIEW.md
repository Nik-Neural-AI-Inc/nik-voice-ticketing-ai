# Third-Party Service Review & Data Processing Assessment
# Nik Voice Ticketing AI v1.4.1

**Document Reference:** TPR-REVIEW-v1.4.1  
**Last Assessment Date:** October 2026  
**Reviewing Team:** Information Security & Privacy Engineering  
**Scope:** External endpoints, AI transcription backends, API gateways, and telemetry services utilized by Nik Voice Ticketing AI.

---

## 1. Overview & Vendor Governance

Nik Voice Ticketing AI allows site operators to process customer voice tickets locally or connect to cloud-based speech-to-text (STT) and AI categorization engines. When external services are enabled, audio files and text metadata are transmitted across network boundaries.

This document reviews the data handling, privacy commitments, terms of service, and technical safeguards associated with each supported external vendor.

```
+-----------------------------------------------------------------------------------+
|                         THIRD-PARTY DATA FLOW ARCHITECTURE                        |
+-----------------------------------------------------------------------------------+
|                                                                                   |
|  [ WordPress Site ]                                                               |
|        |                                                                          |
|        +---- (1) Primary AI Engine: Nik Voice Cloud (Vancouver/Calgary, Canada)   |
|        |     Endpoint: https://api.nikneural.ca/v1/voice/process                  |
|        |     Payload: Encrypted WebM/WAV stream + Ticket ID                       |
|        |                                                                          |
|        +---- (2) Alternative AI: OpenAI Whisper (Optional / BYOK)                 |
|        |     Endpoint: https://api.openai.com/v1/audio/transcriptions             |
|        |     Payload: Audio buffer                                                |
|        |                                                                          |
|        +---- (3) Alternative Moderation: Modulate.ai (Optional / ToxMod)          |
|        |     Endpoint: https://api.modulate.ai/v1/analyze                         |
|        |     Payload: Audio stream for toxicity / safety screening                |
|        |                                                                          |
|        +---- (4) Diagnostic Telemetry: Nik Neural Systems (Opt-In Only)           |
|              Endpoint: https://api.nikneural.ca/v1/telemetry                      |
|              Payload: Anonymized WP/PHP version, active features (NO PII)         |
+-----------------------------------------------------------------------------------+
```

---

## 2. Primary Service: Nik Voice Cloud (Nik Neural AI Inc.)

### 2.1 Service Overview
- **Operator**: Nik Neural AI Inc., British Columbia, Canada.
- **Service Function**: Automatic Speech Recognition (ASR), multi-language transcription, sentiment scoring, and automated department categorization.
- **Processing Architecture**: Sovereign Canadian Cloud infrastructure hosted in Canadian data centers (Vancouver & Calgary). AI acceleration is powered by dedicated Groq Whisper-v3 LPU/TPU hardware.
- **Official Portal**: [https://nikneural.ca/voicedesk-ai/](https://nikneural.ca/voicedesk-ai/)
- **Privacy Policy**: [https://nikneural.ca/privacy/](https://nikneural.ca/privacy/) (Reviewed October 2026).
- **Terms of Service**: [https://nikneural.ca/terms/](https://nikneural.ca/terms/) (Reviewed October 2026).

### 2.2 Data Processing & Privacy Assessment
- **Data Residency**: Canadian data residency infrastructure for voice.nikneural.ca. Data processed via Canadian gateway endpoints.
- **Retention Policy**: Ephemeral by default. Audio streams processed through standard API tiers are held in memory during inference and permanently purged immediately upon completion of the transcription response.
- **Model Training**: Customer audio streams, transcripts, and metadata are **never** used to train, fine-tune, or improve foundational machine learning models.
- **Encryption**:
  - In Transit: TLS 1.2 / TLS 1.3 as negotiated with the gateway endpoint.
  - At Rest: If temporary diagnostic spooling is configured by enterprise contract, data is encrypted via AES-256.
- **Canadian Regulatory Alignment**: Designed to assist organizations in meeting their obligations under the Personal Information Protection and Electronic Documents Act (PIPEDA) and British Columbia Personal Information Protection Act (BC PIPA).

---

## 3. Alternative Service: OpenAI API (Bring-Your-Own-Key)

### 3.1 Service Overview
- **Operator**: OpenAI, LLC, San Francisco, CA, USA.
- **Service Function**: Optional alternative provider for Whisper speech recognition and GPT-based ticket categorization.
- **Endpoints**: `https://api.openai.com/v1/audio/transcriptions`, `https://api.openai.com/v1/chat/completions`.
- **Privacy Policy**: [https://openai.com/policies/privacy-policy](https://openai.com/policies/privacy-policy) (Reviewed October 2026).
- **Business Terms / DPA**: [https://openai.com/policies/business-terms](https://openai.com/policies/business-terms) (Reviewed October 2026).

### 3.2 Data Processing & Privacy Assessment
- **Data Residency**: Data centers located primarily in the United States. Enabling OpenAI triggers a cross-border data transfer.
- **Retention Policy**: Standard API data is retained for up to 30 days for abuse and misuse monitoring, after which it is deleted (unless legal obligations require longer retention). Zero-Data Retention (ZDR) is available for eligible enterprise agreements.
- **Model Training**: As of March 2023, OpenAI explicitly does **not** use customer data submitted through their API to train OpenAI models.
- **Encryption**: TLS 1.2+ in transit; AES-256 at rest.
- **Compliance Impact**: Sites bound by strict domestic Canadian residency requirements (e.g., Canadian public sector, healthcare, or financial institutions) should evaluate cross-border data transfer implications prior to enabling OpenAI.

---

## 4. Alternative Service: Modulate.ai (ToxMod)

### 4.1 Service Overview
- **Operator**: Modulate Inc., Cambridge, MA, USA.
- **Service Function**: Optional enterprise voice moderation and acoustic emotion analysis.
- **Endpoint**: `https://api.modulate.ai/v1/`
- **Privacy Policy**: [https://www.modulate.ai/privacy-policy](https://www.modulate.ai/privacy-policy) (Reviewed October 2026).

### 4.2 Data Processing & Privacy Assessment
- **Data Residency**: United States infrastructure (AWS US East).
- **Retention Policy**: Real-time acoustic triage. Audio buffers are discarded post-inference unless flagged for severe safety violations per customer-defined moderation thresholds.
- **Model Training**: Anonymized acoustic features may be processed for safety classifier improvements unless enterprise opt-out is negotiated.
- **Encryption**: TLS 1.3 in transit; AES-256 at rest.

---

## 5. First-Party Telemetry Service

### 5.1 Service Overview
- **Destination**: `https://api.nikneural.ca/v1/telemetry`
- **Frequency**: Once weekly via WP-Cron (`nikvotia_weekly_telemetry_event`).
- **Authorization**: **Strictly Opt-In**. Telemetry is disabled by default upon installation. It is activated only if the site administrator explicitly checks "Enable Diagnostic Telemetry" in Settings.

### 5.2 Payload Inspection & PII Verification
The telemetry payload is strictly limited to non-identifying operational metrics. An inspection of `class-nikvotia-telemetry.php` confirms the exact schema:

```json
{
  "site_hash": "a6c8b74f...",
  "plugin_version": "1.4.0",
  "wp_version": "7.1.3",
  "php_version": "8.2.12",
  "web_server": "Apache/2.4.58",
  "active_theme": "twentytwentyfive",
  "audio_count_lifetime": 42,
  "telemetry_timestamp": 1729209600
}
```

**Excluded Data**:
- No user names, email addresses, or IP addresses.
- No site domain or home URL (only a one-way irreversible SHA-256 hash).
- No ticket titles, descriptions, audio recordings, or transcripts.
- No API keys, credentials, or HMAC secrets.

---

## 6. Vendor Comparison Matrix

| Evaluation Criteria | Nik Voice Cloud (Primary) | OpenAI Whisper (Optional) | Modulate.ai (Optional) | Telemetry Service |
| :--- | :--- | :--- | :--- | :--- |
| **Physical Processing Location** | Canada (Vancouver/Calgary) | United States | United States | Canada |
| **Data Retention Default** | Ephemeral (0 days) | 30 Days (Abuse monitoring) | Ephemeral (unless flagged) | 90 Days Aggregate |
| **Model Training on Customer Data** | Never | Never (API data excluded) | Restricted / Opt-out | Never (Metrics only) |
| **Transport Layer Security** | TLS 1.2+ / TLS 1.3 | TLS 1.2+ | TLS 1.2+ / TLS 1.3 | TLS 1.2+ / TLS 1.3 |
| **Cross-Border Transfer from Canada**| No (Domestic) | Yes (Canada -> US) | Yes (Canada -> US) | No (Domestic) |
| **Canadian Privacy Framework Alignment** | High (Domestic gateway) | Requires Transfer Assessment| Requires Transfer Assessment| High (No PII) |

---

## 7. Administrator Recommendations for Third-Party Governance

1. **Conduct a Privacy Impact Assessment (PIA)**: Prior to selecting an external AI provider, assess whether your organization's regulatory regime (e.g., PIPEDA, BC PIPA, Law 25, GDPR) permits cross-border voice data transmission.
2. **Obtain Explicit End-User Consent**: Voice recordings constitute biometric and personal information. Ensure your frontend submission notice clearly informs users which third-party AI provider will process their audio.
3. **Execute Data Processing Agreements (DPAs)**: Enterprise operators should ensure formal DPAs are active with Nik Neural AI Inc. or any alternate selected provider.
4. **Regularly Audit Connected Keys**: Rotate API keys every 90 to 180 days within the Settings > API Configuration tab.
