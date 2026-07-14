# Weather Voice Agent

A production-ready voice agent that lets users call in to check the weather. When it's cold (<10°C / 50°F), it sends an SMS coat reminder via Twilio.

Built with **Laravel 11 (PHP 8.2+)**, deployed via **Laravel Forge**, **Railway**, **Heroku**, or any PHP-capable host.

## Architecture

```
Phone → Retell AI → Laravel API → Open-Meteo (weather)
                               → Twilio (SMS if cold)
```

## Setup

### Prerequisites

- [Retell AI](https://retellai.com) account
- [Twilio](https://twilio.com) account (free trial works)
- PHP 8.2+, Composer
- A Laravel-compatible host (Laravel Forge, Railway, Heroku, DigitalOcean, VPS, etc.)

### 1. Install Dependencies

```bash
cd weather-voice-agent
composer install
```

### 2. Configure Environment

Copy `.env.example` to `.env` and fill in your values:

```bash
cp .env.example .env
php artisan key:generate
```

| Variable | Description |
|----------|-------------|
| `APP_URL` | Your app's public URL |
| `TWILIO_ACCOUNT_SID` | From Twilio Console |
| `TWILIO_AUTH_TOKEN` | From Twilio Console |
| `TWILIO_PHONE_NUMBER` | Twilio phone number (E.164, e.g., `+1234567890`) |
| `COLD_THRESHOLD_CELSIUS` | Default: `10` |

### 3. Set Up Twilio

1. Sign up at [twilio.com](https://twilio.com)
2. Get a phone number (Console → Phone Numbers → Buy a Number)
3. In Twilio Console, verify the phone numbers you'll send SMS to (trial requirement)
4. Copy Account SID, Auth Token, and phone number to your `.env` file

### 4. Deploy

**Option A — Laravel Forge (recommended):**
1. Push the repo to GitHub/GitLab
2. Connect to Forge, create a site, deploy
3. Set environment variables in Forge dashboard

**Option B — Railway:**
1. Connect repo to Railway
2. Set build command: `composer install`
3. Set start command: `php artisan serve --host=0.0.0.0 --port=$PORT`

**Option C — Manual VPS:**
```bash
composer install --optimize-autoloader --no-dev
# Point your web server (Nginx) to public/
```

### 5. Configure Retell AI Agent

1. Log in to [Retell Dashboard](https://retellai.com/dashboard)
2. Create a new **Voice Agent**
3. **LLM Settings**:
   - Provider: OpenAI
   - Model: `gpt-4o-mini`
   - Temperature: 0.3
   - System Prompt: Use the content from `retell-agent-prompt.md`
4. **Tools** → Add Tool:
   - Name: `check_weather`
   - Type: Webhook
   - URL: `https://YOUR_APP_URL.com/api/weather-check`
   - Method: POST
   - Parameters (JSON Schema):
     ```json
     {
       "type": "object",
       "properties": {
         "city": { "type": "string", "description": "City name" },
         "country": { "type": "string", "description": "Optional country/state" }
       },
       "required": ["city"]
     }
     ```
5. **Voice Settings**: Use `retell-llama-3.1-8b` or your preferred voice
6. **Phone Number**: Purchase or connect a phone number in Retell
7. Save and activate the agent

### 6. Test

1. Call the Retell phone number
2. Say your city name (e.g., "London")
3. Hear the weather result
4. If cold: check your phone for the SMS coat reminder

## API Reference

### POST /api/weather-check

**Request:**
```json
{
  "city": "London",
  "country": "UK",
  "callerPhone": "+14155551234"
}
```

**Response (cold):**
```json
{
  "temperature": 6.2,
  "unit": "celsius",
  "condition": "Overcast",
  "windSpeed": 11.3,
  "coldAlertSent": true,
  "location": "London, England UK",
  "message": "The temperature in London, England UK is 6.2°C with overcast. A cold weather alert has been sent to your phone."
}
```

**Response (warm):**
```json
{
  "temperature": 22.5,
  "unit": "celsius",
  "condition": "Clear sky",
  "windSpeed": 5.1,
  "coldAlertSent": false,
  "location": "Paris, Île-de-France FR",
  "message": "The temperature in Paris, Île-de-France FR is 22.5°C with clear sky. Nice weather — no coat needed today!"
}
```

**Error responses** use HTTP status codes 400, 404, or 500 with an `error` field and human-readable `message`.

## Running Tests

```bash
./vendor/bin/phpunit
```

Tests cover:
- Cold threshold logic (boundary conditions)
- SMS decision logic
- Message template formatting
- Request validation
- Input validation (Laravel form request)

## Alternative: n8n Workflow

Instead of the Laravel app, import `n8n-workflow.json` into your n8n instance:

1. Create a new workflow → Import from JSON
2. Set up the webhook trigger URL
3. Configure Twilio credentials in n8n
4. Activate the workflow
5. Point the Retell tool to the n8n webhook URL instead

## Project Structure

```
weather-voice-agent/
├── FUNCTIONAL_ANALYSIS.md        # Full functional analysis document
├── app/
│   ├── Http/Controllers/Api/
│   │   └── WeatherCheckController.php  # Main endpoint
│   ├── Services/
│   │   ├── WeatherService.php          # Open-Meteo API client
│   │   └── SMSService.php              # Twilio SMS client
│   └── Exceptions/
│       └── WeatherException.php        # Custom exception
├── routes/
│   └── api.php                    # Route definition
├── config/
│   ├── app.php                    # Laravel app config
│   └── services.php               # Twilio & weather config
├── tests/
│   ├── Unit/
│   │   └── WeatherLogicTest.php   # Logic & threshold tests
│   └── Feature/
│       └── WeatherCheckValidationTest.php  # Request validation
├── retell-agent-prompt.md         # System prompt for the voice agent
├── retell-agent-config.json       # Retell agent configuration reference
├── n8n-workflow.json              # Alternative n8n workflow
├── composer.json
├── phpunit.xml
└── .env.example
```

## Edge Cases Handled

- City not found → agent asks for clarification
- Ambiguous city name → agent offers options
- Twilio misconfigured → alert not sent, user still gets weather info
- No caller phone number → informs user, no SMS sent
- Weather API down → graceful error, retry advised
- Temperature exactly at threshold (10°C) → no alert (strictly below)

## Monitoring

Monitor via:
- **Retell Dashboard**: Call logs, duration, success rate
- **Laravel logs** (`storage/logs/laravel.log`): Application errors
- **Twilio Console**: SMS delivery status, error logs