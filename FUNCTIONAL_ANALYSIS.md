Weather Voice Agent — Functional Analysis

1. Customer Goals

Receive weather reports ands suggestions. Client wants to be able to call a phone number, speak with a agent and retrieve accurate weather reports via call and SMS.

2. Architecture Overview
Phone caller calls Voice Agent (VA)
VA collects information about city, phone number and country (optionalk)
VA calls backend (BE) service with gathered information 
BE calls geolocation services and weather services in order to get weather report
BE returns weather report to VA 
VA communicates report to customer
BE determines if conditions are met in order to send alerts to caller's phone via SMS

3. Component Stack
VA: Retell AI
BE: Laravel + PHP
Geolocation and Weather Service: open-meteo.com API

| Component | Technology | Purpose |
|-----------|-----------|---------|
| Voice Agent | Retell AI (retellai.com) | STT, TTS, LLM conversation, phone number provisioning |
| Backend API | Laravel 11 (PHP 8.2+) | HTTP endpoint, business logic orchestration |
| Weather Data | Open-Meteo (open-meteo.com) | Free geocoding + current weather, no API key needed |
| SMS Service | Twilio | Programmable SMS with trial credit |
| Email Service | PHPMailer via Gmail SMTP | Sends weather report to hardcoded email address |
| Web Server | Nginx + PHP-FPM | Reverse proxy, PHP process management |


4. Future features:

- Add multiple services for backup
- Add multiple notification mechanism
- Allow user to configure alert conditions with VA
- CRON job for alerts instead of on demand
- retry mechanism for SMS/mail delivery
