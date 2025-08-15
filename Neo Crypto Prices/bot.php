<?php

/**
 * Telegram Cryptocurrency Bot
 * * This bot provides real-time cryptocurrency information using the CoinGecko API.
 * It is a direct translation of a Cloudflare Worker script and handles various
 * commands to fetch crypto prices, market statistics, and trending coins.
 */

// Your Telegram Bot Token from BotFather (https://t.me/botfather)
const TELEGRAM_BOT_TOKEN = 'token bot';


const WEBHOOK_ENDPOINT = '/webhook';


const COINGECKO_API = 'https://api.coingecko.com/api/v3';


if (isset($_SERVER['REQUEST_URI']) && isset($_SERVER['REQUEST_METHOD'])) {
    handleRequest();
}
function handleRequest() {
    $path = strtok($_SERVER['REQUEST_URI'], '?');
    $method = $_SERVER['REQUEST_METHOD'];
    $workerUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";

    error_log('Incoming request: ' . json_encode(['path' => $path, 'method' => $method]));

    try {
        if ($method === 'POST' && $path === WEBHOOK_ENDPOINT) {
            $update = json_decode(file_get_contents('php://input'), true);
            error_log('Received update: ' . json_encode($update));
            if ($update) {
                handleTelegramUpdate($update);
            }
            http_response_code(200);
            echo 'OK';
        } elseif ($method === 'GET' && $path === '/setwebhook') {
            setWebhook(urlencode($workerUrl . WEBHOOK_ENDPOINT));
        } else {
            http_response_code(404);
            echo 'Not Found';
        }
    } catch (Exception $e) {
        error_log('Request handling error: ' . $e->getMessage());
        http_response_code(500);
        echo 'Internal Server Error';
    }
}

/**
 * Sets up the Telegram webhook URL
 * This needs to be called once to tell Telegram where to send updates
 * * @param string $encodedUrl - URL-encoded webhook endpoint
 */
function setWebhook($encodedUrl) {
    try {
        $webhookUrl = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/setWebhook?url=" . $encodedUrl;
        error_log('Setting webhook URL: ' . $webhookUrl);

        $response = file_get_contents($webhookUrl);
        $result = json_decode($response, true);
        error_log('Webhook setup response: ' . json_encode($result));

        if ($result && $result['ok']) {
            http_response_code(200);
            echo 'Webhook set successfully!';
        } else {
            $description = $result['description'] ?? 'Unknown error';
            error_log('Webhook setup failed: ' . json_encode($result));
            http_response_code(500);
            echo "Failed to set webhook: " . $description;
        }
    } catch (Exception $e) {
        error_log('Webhook setup error: ' . $e->getMessage());
        http_response_code(500);
        echo 'Failed to set webhook';
    }
}

/**
 * Handles incoming Telegram updates and routes different commands
 * * @param array $update - Telegram update object
 */
function handleTelegramUpdate($update) {
    if (!isset($update['message']) || !isset($update['message']['text'])) {
        error_log('Invalid update received: ' . json_encode($update));
        return;
    }

    $chatId = $update['message']['chat']['id'];
    $text = strtolower($update['message']['text']);
    error_log('Processing message: ' . json_encode(['chatId' => $chatId, 'text' => $text]));

    try {
        if ($text === '/start' || $text === '/help') {
            sendMessage($chatId,
                'Welcome to the Crypto Price Bot\! 🚀\n\n' .
                'Available commands:\n' .
                '/price \\<coin\\> \\- Get price for a specific coin \\(e\\.g\\., /price bitcoin\\)\n' .
                '/top10 \\- Get top 10 cryptocurrencies by market cap\n' .
                '/trending \\- Show trending coins\n' .
                '/global \\- Show global market stats\n' .
                '/help \\- Show this help message'
            );
        } elseif ($text === '/top10') {
            handleTop10Command($chatId);
        } elseif ($text === '/trending') {
            handleTrendingCommand($chatId);
        } elseif ($text === '/global') {
            handleGlobalCommand($chatId);
        } elseif (strpos($text, '/price ') === 0) {
            $parts = explode(' ', $text, 2);
            $coin = $parts[1] ?? '';
            if ($coin) {
                handlePriceCommand($chatId, $coin);
            }
        }
    } catch (Exception $e) {
        error_log('Error handling update: ' . $e->getMessage());
        sendMessage($chatId, '❌ Sorry, an error occurred\. Please try again later\.');
    }
}

/**
 * Fetches and formats global cryptocurrency market statistics
 * * @param int $chatId - Telegram chat ID
 */
function handleGlobalCommand($chatId) {
    try {
        $response = file_get_contents(COINGECKO_API . '/global');
        if ($response === false) {
            throw new Exception("API error: Could not fetch global data");
        }

        $data = json_decode($response, true);
        if (!$data || !isset($data['data'])) {
            throw new Exception("API error: Invalid global data received");
        }

        $stats = $data['data'];
        $message =
            '🌍 *Global Crypto Market Stats*\n\n' .
            'Total Market Cap: $' . escapeMarkdown(number_format($stats['total_market_cap']['usd'])) . '\n' .
            '24h Volume: $' . escapeMarkdown(number_format($stats['total_volume']['usd'])) . '\n' .
            'BTC Dominance: ' . escapeMarkdown(number_format($stats['market_cap_percentage']['btc'], 2)) . '\%\n' .
            'Active Cryptocurrencies: ' . escapeMarkdown((string)$stats['active_cryptocurrencies']) . '\n' .
            'Markets: ' . escapeMarkdown((string)$stats['markets']);

        sendMessage($chatId, $message);
    } catch (Exception $e) {
log_error_and_send_message($chatId, 'global stats', $e->getMessage());
    }
}

/**
 * Fetches and displays currently trending cryptocurrencies
 * * @param int $chatId - Telegram chat ID
 */
function handleTrendingCommand($chatId) {
    try {
        $response = file_get_contents(COINGECKO_API . '/search/trending');
        if ($response === false) {
            throw new Exception("API error: Could not fetch trending data");
        }

        $data = json_decode($response, true);
        if (!$data || !isset($data['coins'])) {
            throw new Exception("API error: Invalid trending data received");
        }

        $message = '🔥 *Trending Cryptocurrencies*\n\n';
        foreach ($data['coins'] as $index => $item) {
            $coin = $item['item'];
            $message .= ($index + 1) . '\. ' . escapeMarkdown($coin['name']) . ' \(' . escapeMarkdown(strtoupper($coin['symbol'])) . '\)\n' .
                'Market Cap Rank: \#'. $coin['market_cap_rank'] . '\n' .
                'Price BTC: ' . escapeMarkdown(number_format($coin['price_btc'], 8)) . '\n\n';
        }

        sendMessage($chatId, $message);
    } catch (Exception $e) {
        log_error_and_send_message($chatId, 'trending coins', $e->getMessage());
    }
}

/**
 * Fetches and displays top 10 cryptocurrencies by market cap
 * * @param int $chatId - Telegram chat ID
 */
function handleTop10Command($chatId) {
    try {
        $context = stream_context_create([
            'http' => [
                'header' => "Accept: application/json\r\n" .
                            "User-Agent: Telegram Bot\r\n"
            ]
        ]);
        $url = COINGECKO_API . '/coins/markets?vs_currency=usd&order=market_cap_desc&per_page=10&page=1&sparkline=false';
        $response = file_get_contents($url, false, $context);

        if ($response === false) {
            throw new Exception("CoinGecko API error: Could not fetch top 10 data");
        }

        $data = json_decode($response, true);
        if (!$data) {
            throw new Exception("CoinGecko API error: Invalid top 10 data received");
        }

        $message = '📊 *Top 10 Cryptocurrencies*\n\n';
        foreach ($data as $index => $coin) {
            $priceChange = $coin['price_change_percentage_24h'] ?? 0;
            $priceChangeIcon = $priceChange >= 0 ? '🟢' : '🔴';

            $message .= ($index + 1) . '\. ' . escapeMarkdown($coin['name']) . ' \(' . escapeMarkdown(strtoupper($coin['symbol'])) . '\)\n';
            $message .= '💵 Price: $' . escapeMarkdown(number_format($coin['current_price'])) . '\n';
            $message .= $priceChangeIcon . ' 24h: ' . escapeMarkdown(number_format($priceChange, 2)) . '\%\n';
            $message .= '💎 Market Cap: $' . escapeMarkdown(number_format($coin['market_cap'])) . '\n';
            $message .= '📊 Volume: $' . escapeMarkdown(number_format($coin['total_volume'])) . '\n\n';
        }

        sendMessage($chatId, $message);
    } catch (Exception $e) {
        log_error_and_send_message($chatId, 'top 10 cryptocurrencies', $e->getMessage());
    }
}

/**
 * Fetches and displays detailed information about a specific cryptocurrency
 * * @param int $chatId - Telegram chat ID
 * @param string $coin - Name or symbol of the cryptocurrency to look up
 */
function handlePriceCommand($chatId, $coin) {
    try {
        $context = stream_context_create([
            'http' => [
                'header' => "Accept: application/json\r\n" .
                            "User-Agent: Telegram Bot\r\n"
            ]
        ]);

        $searchUrl = COINGECKO_API . '/search?query=' . urlencode($coin);
        $searchResponse = file_get_contents($searchUrl, false, $context);
        if ($searchResponse === false) {
            throw new Exception("CoinGecko API search error");
        }

        $searchData = json_decode($searchResponse, true);
        if (!$searchData || !isset($searchData['coins']) || empty($searchData['coins'])) {
            sendMessage($chatId, '❌ Could not find cryptocurrency: ' . escapeMarkdown($coin));
            return;
        }

        $coinId = $searchData['coins'][0]['id'];
        $detailsUrl = COINGECKO_API . "/coins/{$coinId}?localization=false&tickers=false&market_data=true&community_data=false&developer_data=false";
        $response = file_get_contents($detailsUrl, false, $context);
        if ($response === false) {
            throw new Exception("CoinGecko API details error");
        }

        $data = json_decode($response, true);
        if (!$data) {
            throw new Exception("Invalid coin details data received");
        }

        $priceChange = $data['market_data']['price_change_percentage_24h'] ?? 0;
        $priceChangeIcon = $priceChange >= 0 ? '🟢' : '🔴';

        $message = '💰 ' . escapeMarkdown($data['name']) . ' \(' . escapeMarkdown(strtoupper($data['symbol'])) . '\)\n\n' .
            'Current Price: $' . escapeMarkdown(number_format($data['market_data']['current_price']['usd'])) . '\n' .
            $priceChangeIcon . ' 24h Change: ' . escapeMarkdown(number_format($priceChange, 2)) . '\%\n' .
            '📈 24h High: $' . escapeMarkdown(number_format($data['market_data']['high_24h']['usd'])) . '\n' .
            '📉 24h Low: $' . escapeMarkdown(number_format($data['market_data']['low_24h']['usd'])) . '\n' .
            '💎 Market Cap: $' . escapeMarkdown(number_format($data['market_data']['market_cap']['usd'])) . '\n' .
            '📊 Market Cap Rank: \#' . $data['market_cap_rank'] . '\n' .
            '💫 Volume: $' . escapeMarkdown(number_format($data['market_data']['total_volume']['usd']));

        sendMessage($chatId, $message);
    } catch (Exception $e) {
        log_error_and_send_message($chatId, 'price for ' . $coin, $e->getMessage());
    }
}

/**
 * Escapes special characters for Telegram's MarkdownV2 format
 * * @param string|null $text - Text to escape
 * @return string Escaped text
 */
function escapeMarkdown($text) {
    if ($text === null) return '';
    $text = (string)$text;
    return preg_replace('/([_*[\]()~`>#+=|{}.!-])/u', '\\\\$1', $text);
}

/**
 * Sends a message to a Telegram chat
 * * @param int $chatId - Telegram chat ID
 * @param string $text - Message text (with MarkdownV2 formatting)
 */
function sendMessage($chatId, $text) {
    try {
        $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendMessage";
        error_log('Sending message: ' . json_encode(['chatId' => $chatId, 'text' => $text]));

        $options = [
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/json\r\n",
                'content' => json_encode([
                    'chat_id' => $chatId,
                    'text' => $text,
                    'parse_mode' => 'MarkdownV2'
                ])
            ]
        ];
        $context = stream_context_create($options);
        $response = file_get_contents($url, false, $context);
        
        if ($response === false) {
            throw new Exception("Failed to send message via Telegram API");
        }

        $responseData = json_decode($response, true);
        error_log('Telegram API response: ' . json_encode($responseData));

        if (!$responseData['ok']) {
            throw new Exception('Telegram API error: ' . ($responseData['description'] ?? 'Unknown error'));
        }
    } catch (Exception $e) {
        error_log('Error sending message: ' . $e->getMessage());
    }
}

/**
 * Helper function to log errors and send a simple message to the user
 * * @param int $chatId - Telegram chat ID
 * @param string $action - The action that failed
 * @param string $error - The error message
 */
function log_error_and_send_message($chatId, $action, $error) {
    error_log("Error fetching $action: " . $error);
    sendMessage($chatId, "❌ Failed to fetch $action\. Please try again later\.");
}


//amir hosein nouri tnx 