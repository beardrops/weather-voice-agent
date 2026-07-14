1. Customer goals

- user wants to call voice-agent(VA) in order to receive details about current weather at his/her location.
- user wants to have specific, weather related, trigger to receive helpful reminders.

2. Architecture overview

- user calls and communicates with VA
- VA identifies user location and phone number
- VA calls server API with location and phone number
- server requests current weather information from 3rd party service provider
- server responds to VA with human formatted text
- server determines edge case scenarios that require SMS to be sent
- server sends SMS

3. Components
- VA: Retell
- 

| Goal | Details |
|------|---------|
| Check weather by voice | User calls a phone number, speaks their town/city, and hears the current temperature |
| Cold-weather alert | If temperature < 10°C (50°F), send an SMS reminder to bring a coat |
| Global reach | Agent works for any town/city worldwide |
| Zero-config calls | User dials in, speaks naturally, gets result — no app or setup needed |

## 2. Architecture Overview

```
┌──────────┐     ┌──────────────┐     ┌────────────────┐     ┌───────────┐
│  Phone   │────▶│  Retell AI   │────▶│  Serverless    │────▶│ Open-Meteo│
│  Caller  │     │  Voice Agent │     │  Function      │     │ (Weather) │
└──────────┘     └──────────────┘     └────────────────┘     └───────────┘
                      │                       │
                      │                       ├────────────────────┐
                      │                       │                    │
                      │                  ┌────▼────┐        ┌─────▼──────┐
                      │                  │ Twilio  │        │  SMS/Email │
                      │                  │  SMS    │        │  Fallback  │
                      │                  └─────────┘        └────────────┘
                      │
                      ▼
               ┌──────────────┐
               │  Caller hears │
               │ weather +     │
               │ confirmation  │
               └──────────────┘
```

### Component Stack

| Component | Technology | Rationale |
|-----------|-----------|-----------|
| Voice Agent | Retell AI (retellai.com) | Handles STT/TTS/LLM conversation, phone number provisioning |
| Backend Workflow | Node.js serverless function (Vercel) | Zero maintenance, free tier, simple HTTP handler |
| Weather API | Open-Meteo (open-meteo.com) | Free, no API key, global coverage, no rate limits for low volume |
| SMS Service | Twilio | Free trial credit, reliable, well-documented |
| (Alternative) SMS | Email-to-SMS gateway | Free, carrier-dependent |
| (Alternative) Workflow | n8n self-hosted | Visual editor, lower-code maintenance |

## 3. Inputs

| Input | Source | Type | Example |
|-------|--------|------|---------|
| Caller phone number | Retell (from inbound call) | String (E.164) | `+14155551234` |
| Town/city name | User speech → Retell STT | String | `"London"` or `"New York"` |
| Country / state (optional) | User speech → Retell STT | String | `"UK"` or `"New York"` (disambiguation) |

## 4. Components Detail

### 4.1 Retell AI Voice Agent

**Configuration:**
- **LLM**: GPT-4o-mini or Claude 3 Haiku (low latency, sufficient for simple task)
- **System Prompt** (see `retell-agent-prompt.md`)
- **Tools/Webhooks**: One custom tool → POST to serverless function
- **Model**: `retell-llama-3.1-8b` or bring-your-own LLM via function calling

**Conversation Flow:**
1. Greeting: "Hello! I'm your weather assistant. Which town or city would you like to check the weather for?"
2. User speaks city name
3. Agent confirms: "Checking weather for {city}..."
4. Agent calls webhook (async) with `{ city, callerPhoneNumber }`
5. Agent reads result: "The temperature in {city} is currently {temp}°C. {SMS confirmation}"
6. Closing: "Is there anything else I can help with?"

**Edge Cases Handled by Prompt:**
- User says "I don't know" → prompt asks for any nearby city
- User says multiple cities → pick the first one
- Ambiguous city names → ask for country/state clarification
- Non-English city names → accept any language, pass as-is to API

### 4.2 Serverless Function

**Endpoint:** `POST /api/weather-check`

**Request:**
```json
{
  "city": "London",
  "country": "UK",
  "callerPhone": "+14155551234"
}
```

**Response:**
```json
{
  "temperature": 7.2,
  "unit": "celsius",
  "condition": "Overcast",
  "coldAlertSent": true,
  "message": "The temperature in London is 7.2°C. A cold alert SMS has been sent to your phone."
}
```

**Logic (pseudocode):**
```
1. Receive { city, callerPhone }
2. Geocode city → lat/lng via Open-Meteo Geocoding API
   - If ambiguous, return 400 "Multiple matches found, please be more specific"
   - If no match, return 404 "City not found"
3. Fetch current weather from Open-Meteo API at lat/lng
4. Extract current temperature_2m
5. If temperature < 10°C:
   a. Send SMS via Twilio: "🧥 Cold weather alert! It's {temp}°C in {city} today. Don't forget your coat!"
   b. Set coldAlertSent = true
6. Return { temperature, condition, coldAlertSent }
```

### 4.3 Open-Meteo API

**Geocoding:** `GET https://geocoding-api.open-meteo.com/v1/search?name={city}&count=5&language=en&format=json`

**Weather:** `GET https://api.open-meteo.com/v1/forecast?latitude={lat}&longitude={lng}&current_weather=true&temperature_unit=celsius`

**Free tier:** No API key, 10,000 requests/day (more than sufficient for this use case).

### 4.4 Twilio SMS

**Account:** Free trial ($15 credit, ~1,500 SMS messages).
**Requirement:** Verify the caller's phone number as a "verified caller ID" in Twilio console for trial mode.
**Production:** Upgrade to paid account to send to any unverified number.

## 5. Flows

### 5.1 Happy Path

```
Caller dials number
  │
  ▼
Retell answers → "Hello! Which town or city?"
  │
  ▼
Caller: "Paris"
  │
  ▼
Retell calls webhook → Serverless function
  │
  ▼
Geocode "Paris" → lat=48.8566, lng=2.3522
  │
  ▼
Fetch weather → temp=6.2°C (below 10°C)
  │
  ▼
Send SMS via Twilio → "🧥 Cold weather alert! It's 6.2°C in Paris..."
  │
  ▼
Return result to Retell → "The temperature in Paris is 6.2°C. I've sent a coat reminder to your phone."
  │
  ▼
Caller hears result → call ends
```

### 5.2 Warm Weather (No SMS)

```
...geocode → fetch weather → temp=22°C (above 10°C)
  │
  ▼
No SMS sent
  │
  ▼
Return → "The temperature in Paris is 22°C. Nice weather! No coat needed today."
```

### 5.3 City Not Found

```
...geocode → no match
  │
  ▼
Return error → Retell asks: "I couldn't find that city. Could you try again with a different name or include a country?"
```

### 5.4 Ambiguous City

```
...geocode → multiple matches (Springfield, IL vs Springfield, MA)
  │
  ▼
Return info → Retell asks: "There are several places called Springfield. Which state or country?"
```

## 6. Triggers

| Trigger | Source | Action |
|---------|--------|--------|
| Inbound phone call | PSTN → Retell | Start voice agent conversation |
| User speaks city name | Retell STT → LLM | Extract city, call webhook |
| Webhook response | Serverless function | Read result to user, send SMS if cold |
| Temperature < 10°C | Serverless logic | Send Twilio SMS |
| API failure (weather) | Open-Meteo down | Retell apologizes, asks to try again later |

## 7. Logic — Detailed

### Temperature Threshold

```
coldThreshold = 10°C (fixed, per customer requirement)
```

**Why 10°C?** Customer specified. Could be made configurable via environment variable `COLD_THRESHOLD_CELSIUS` for future flexibility.

### SMS Message Template

```
Subject: Weather Alert
Body: 🧥 Cold weather alert! It's {temperature}°C in {city} today ({condition}). Don't forget your coat!
```

### Retry Strategy

| Operation | Retries | Backoff | Fallback |
|-----------|---------|---------|----------|
| Geocoding API | 1 | None | Return "city not found" |
| Weather API | 2 | 500ms, 2s | Return "service unavailable" |
| Twilio SMS | 2 | 1s, 3s | Log error, return coldAlertSent=false |

## 8. Edge Cases

| Case | Handling |
|------|----------|
| City not found by geocoding | Retell asks user to rephrase or be more specific |
| Multiple cities with same name | Retell asks for country/state to disambiguate |
| Temperature exactly 10°C | Not cold (threshold is strictly below 10°C) |
| User caller ID blocked | Retell provides `unknown` — ask user for SMS number during call |
| International number (non-US) | Twilio supports global SMS. Verify regional compliance. |
| Weather API rate-limited | Rare at low volume. Retry with exponential backoff. |
| SMS fails | Log error, still tell user the temperature. Don't retry indefinitely. |
| Non-English city name | Open-Meteo geocoding handles international names. Pass through. |
| Very long city name | Truncate SMS gracefully (Twilio concatenates long messages automatically) |
| User hangs up mid-request | Webhook still runs, SMS sent independently of call state |
| Network failure on webhook | Retell has no built-in retry for tool calls. Function must be idempotent. |

## 9. Unit Tests

### Weather Logic Tests

| Test | Input | Expected |
|------|-------|----------|
| Below threshold | temp=5°C | coldAlertSent=true |
| At threshold | temp=10°C | coldAlertSent=false |
| Above threshold | temp=25°C | coldAlertSent=false |
| Below threshold (F) | temp=32°F (0°C) | coldAlertSent=true |

### Geocoding Tests

| Test | Input | Expected |
|------|-------|----------|
| Single match | "London" | lat/lng returned |
| No match | "Atlantis" | error returned |
| Ambiguous | "Springfield" | multiple results returned for disambiguation |

### SMS Tests

| Test | Input | Expected |
|------|-------|----------|
| Valid send | cold=true, valid number | Twilio returns message SID |
| Invalid number | cold=true, invalid number | error logged, coldAlertSent=false |
| Dry run | cold=false | Twilio never called |

## 10. Security Considerations

- **No API keys needed** for Open-Meteo (reduces leak risk)
- **Twilio credentials** stored as environment variables in serverless platform
- **Retell API key** stored as environment variable
- **Caller phone numbers** handled as PII — log only for debugging, no persistent storage
- **HTTPS** enforced on all external API calls
- **Input sanitization** — city name validated, no special characters accepted

## 11. Monitoring & Observability

| Metric | Tool | Action |
|--------|------|--------|
| Number of calls | Retell dashboard | Track usage |
| Webhook errors | Vercel logs / n8n execution logs | Alert on >5% error rate |
| SMS delivery rate | Twilio console | Alert on failed deliveries |
| Cold alert trigger count | Custom metric | Track usage pattern |

## 12. Future Enhancements

- **Multi-language support**: Retell supports multiple languages, prompt can be translated
- **Forecast check**: Check tomorrow's weather, not just current
- **Configurable threshold**: Let user set their own cold threshold via voice
- **Weather alerts**: Subscribe to automated daily weather calls
- **Multiple recipients**: SMS to family members or emergency contacts
- **Calendar integration**: Check if user has an outdoor event scheduled
