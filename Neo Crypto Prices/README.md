# Telegram Crypto Bot (PHP)

This repository contains a simple, yet powerful, Telegram bot written in **PHP** that provides real-time cryptocurrency information. The bot is designed to be easily deployed on a server that supports PHP, making it a flexible and accessible project.

The bot leverages the **CoinGecko API** to fetch up-to-date data on cryptocurrencies, including prices, market statistics, and trending coins.

---

### Features

* **Real-Time Data**: Get current prices and market stats.
* **Simple Setup**: Easily configure your bot token and deploy.
* **Command-Based Interaction**: Use intuitive commands to get information.
* **API Integration**: Connects directly to the CoinGecko API.

---

### Available Commands

* `/start` or `/help`: Displays a list of all available commands and a brief welcome message.
* `/price <coin>`: Fetches the current price and market data for a specific cryptocurrency (e.g., `/price bitcoin`).
* `/top10`: Shows the top 10 cryptocurrencies by market capitalization.
* `/trending`: Lists the top trending coins currently being searched on CoinGecko.
* `/global`: Provides a summary of the global cryptocurrency market, including total market cap and volume.

---

### Setup and Configuration

1.  **Get a Telegram Bot Token**: Talk to **@BotFather** on Telegram to create a new bot and get your API token.
2.  **Get a CoinGecko API Key**: This bot does not require an API key, as it uses the public endpoints.
3.  **Configure the Code**:
    * Update the `TELEGRAM_BOT_TOKEN` constant in the `index.php` file with your token.
4.  **Set the Webhook**:
    * Deploy your PHP script to your server.
    * Navigate to `https://your-domain.com/your-script-name.php/setwebhook` in your browser. This will tell Telegram to send updates to your script's webhook URL.

Feel free to contribute to this project or use it as a starting point for your own PHP Telegram bot!
