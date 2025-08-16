# WeatherBot 🌤️  
**Persian Telegram Weather Bot**

WeatherBot is a Telegram bot written in **PHP** that provides weather information for Persian-speaking users.  
Users can get the current weather by sending a city name in Persian or by using inline keyboard buttons.

---

## ✨ Features
- **Get Weather by City Name**: Type a city name in Persian (e.g., `تهران` or `/weather تهران`) to receive a weather report.  
- **Inline Keyboard**: Quick selection of predefined cities (`تهران`, `ایلام`, `ساری`).  
- **Detailed Weather Info**: Displays temperature, humidity, wind speed, and general description.  
- **Persian Language Support**: All bot responses are in Persian (Farsi).  
- **Easy Configuration**: Just set your **Telegram Bot Token** and **OpenWeatherMap API Key**.  

---

## 📋 Prerequisites
- **PHP** installed on your server.  
- **Telegram Bot Token** (from [@BotFather](https://t.me/BotFather)).  
- **OpenWeatherMap API Key** (free from [OpenWeatherMap](https://openweathermap.org/)).  

---
3. Configure the Script

Open WeatherBot.php and replace the placeholders:
define('BOT_TOKEN', 'your_tm_token');
define('WEATHER_API_KEY', 'your_WEATHER_API_KEY');

---
4. Set up the Webhook

Upload WeatherBot.php to your web server.
Then set the webhook (replace <YOUR_BOT_TOKEN> and <YOUR_DOMAIN>):
https://api.telegram.org/bot<YOUR_BOT_TOKEN>/setWebhook?url=https://<YOUR_DOMAIN>/WeatherBot.php

💡 Usage

Start a conversation with your bot in Telegram.

Send /start to see the welcome message & inline keyboard.

Send a city name in Persian (e.g., تهران) to get the weather.

Use the inline keyboard for quick city selection.

Use /weather command:
/weather tehran
