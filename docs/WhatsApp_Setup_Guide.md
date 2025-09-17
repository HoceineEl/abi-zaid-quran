# WhatsApp Web Integration Setup Guide

## Overview

A simplified WhatsApp Web API integration for the Quran memorization management system. Each authenticated user can create one WhatsApp session and send messages through the Filament admin panel.

## Features

- ✅ One session per user
- ✅ QR code authentication
- ✅ Real-time session status updates
- ✅ Message sending through web interface
- ✅ Message history tracking
- ✅ Auto-polling for connection status

## Requirements

1. **WhatsApp Web API Service**: You need a running WhatsApp Web API service (like whatsapp-web.js)
2. **Environment Configuration**: Set up the required environment variables

## Setup Instructions

### 1. Environment Configuration

Add these variables to your `.env` file:

```bash
# WhatsApp Web API Configuration
WHATSAPP_API_URL=http://localhost:3000
WHATSAPP_API_TOKEN=your_master_api_key
WHATSAPP_WEBHOOK_URL=https://yourdomain.com/webhooks/whatsapp
WHATSAPP_RATE_LIMIT_PER_MINUTE=10
WHATSAPP_BURST_LIMIT=50
WHATSAPP_SESSION_TIMEOUT_HOURS=24
WHATSAPP_MAX_RETRY_ATTEMPTS=3
```

### 2. Run Migrations

The database tables are already migrated. If you need to refresh:

```bash
php artisan migrate
```

### 3. Set Up WhatsApp Web API Service

Install and run a WhatsApp Web API service like whatsapp-web.js:

```bash
# Example with whatsapp-web.js
npm install whatsapp-web.js
# Set up your API server on port 3000
```

### 4. Access the Interface

Navigate to `/association/whats-app-manager` in your Filament admin panel.

## How to Use

### 1. Create a Session

1. Go to the WhatsApp Manager page in the admin panel
2. Click "Create Session"
3. Wait for the QR code to appear

### 2. Connect Your Phone

1. Open WhatsApp on your phone
2. Go to Settings → Linked Devices
3. Tap "Link a Device"
4. Scan the QR code displayed on the screen

### 3. Send Messages

Once connected:
1. Enter the recipient's phone number (with country code, e.g., 966501234567)
2. Type your message
3. Click "Send Message"

## Technical Details

### Database Tables

- `whatsapp_sessions`: Stores session information and connection status
- `whatsapp_message_histories`: Tracks all sent messages

### Key Files

- `app/Models/WhatsAppSession.php`: Session model
- `app/Models/WhatsAppMessageHistory.php`: Message history model
- `app/Services/WhatsAppService.php`: Core service for API communication
- `app/Filament/Association/Pages/WhatsAppManager.php`: Admin interface
- `app/Enums/WhatsAppConnectionStatus.php`: Connection status enum
- `app/Enums/WhatsAppMessageStatus.php`: Message status enum

### API Endpoints Used

- `POST /api/v1/sessions`: Create a new session
- `GET /api/v1/sessions`: Get session status
- `POST /api/v1/messages`: Send messages
- `DELETE /api/v1/sessions/{id}`: Delete session

## Security Notes

1. **API Token**: Keep your `WHATSAPP_API_TOKEN` secure
2. **Rate Limiting**: Respect WhatsApp's rate limits
3. **Session Management**: Each user can only have one active session
4. **Message Logging**: All messages are logged for audit purposes

## Troubleshooting

### QR Code Not Appearing
- Check if the WhatsApp API service is running
- Verify the API URL and token configuration
- Check Laravel logs for errors

### Connection Issues
- Ensure your WhatsApp API service is accessible
- Check network connectivity
- Verify API token permissions

### Message Sending Fails
- Confirm the session is connected
- Check phone number format (include country code)
- Verify message content doesn't exceed limits

## Rate Limits

- Default: 10 messages per minute
- Burst limit: 50 messages
- Configurable via environment variables

## Monitoring

- All operations are logged to Laravel logs
- Message status tracking in database
- Real-time connection status updates