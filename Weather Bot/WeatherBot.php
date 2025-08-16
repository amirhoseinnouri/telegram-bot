<?php


define('BOT_TOKEN', 'your_tm_token');
define('WEATHER_API_KEY', 'your_WEATHER_API_KEY');
define('LOG_FILE', 'bot_log.log'); 


// تابع برای ارسال پیام به تلگرام
function send_telegram_message($chat_id, $text, $reply_markup = null) {
    $url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/sendMessage';
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'Markdown',
        'reply_markup' => $reply_markup
    ];

    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data),
        ],
    ];

    $context  = stream_context_create($options);
    return file_get_contents($url, false, $context);
}


function get_weather_data($city_name) {
    $url = "http://api.openweathermap.org/data/2.5/weather?q={$city_name}&appid=" . WEATHER_API_KEY . "&lang=fa";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200) {
        return json_decode($response, true);
    } else {
        file_put_contents(LOG_FILE, "Error fetching weather data for {$city_name}: " . $response . "\n", FILE_APPEND);
        return false;
    }
}

$update = json_decode(file_get_contents('php://input'), true);

if (isset($update['message'])) {
    $message = $update['message'];
    $chat_id = $message['chat']['id'];
    $text = trim($message['text']);
    
    $cities = [
        'ایلام' => 'Ilam',
        'تهران' => 'Tehran',
        'ساری' => 'Sari',
    ];

    $keyboard = [
        'inline_keyboard' => [
            [['text' => 'تهران', 'callback_data' => 'weather_Tehran']],
            [['text' => 'ایلام', 'callback_data' => 'weather_Ilam']],
            [['text' => 'ساری', 'callback_data' => 'weather_Sari']],
            [['text' => 'ملایر', 'callback_data' => 'weather_Malayer']]
        ]
    ];
    $encoded_keyboard = json_encode($keyboard);

    // پاسخ به دستور /start
    if ($text === '/start') {
        $response_text = "سلام! به ربات هواشناسی خوش آمدید. لطفا یکی از شهرهای زیر را انتخاب کنید یا نام شهر را به انگلیسی یا فارسی وارد کنید (مثال: /weather تهران)";
        send_telegram_message($chat_id, $response_text, $encoded_keyboard);
    }

    else if (strpos($text, '/weather') === 0 || in_array($text, array_keys($cities))) {
        if (strpos($text, '/weather') === 0) {
            $city_persian = trim(str_replace('/weather', '', $text));
        } else {
            $city_persian = $text;
        }

        $city_english = $cities[$city_persian] ?? null;

        if ($city_english) {
            $weather_data = get_weather_data($city_english);
            
            if ($weather_data) {
                $temp_celsius = round($weather_data['main']['temp'] - 273.15);
                
                $response_text = "✨ **پیش‌بینی آب و هوای $city_persian:**\n";
                $response_text .= "🌡️ دما: {$temp_celsius}°C\n";
                $response_text .= "💧 رطوبت: {$weather_data['main']['humidity']}%\n";
                $response_text .= "💨 سرعت باد: {$weather_data['wind']['speed']} m/s\n";
                $response_text .= "☁️ وضعیت: {$weather_data['weather'][0]['description']}\n";
                
                send_telegram_message($chat_id, $response_text);
            } else {
                send_telegram_message($chat_id, "❌ متأسفانه اطلاعاتی برای شهر $city_persian یافت نشد.");
            }
        } else {
            send_telegram_message($chat_id, "لطفا یکی از شهرهای زیر را وارد کنید: ایلام، تهران، ساری، ملایر", $encoded_keyboard);
        }
    }
}
?>