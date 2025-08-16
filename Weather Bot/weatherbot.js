
const TELEGRAM_BOT_TOKEN = 'your_bot_token';
const WEATHER_API_KEY = 'your_weather_api_key';


addEventListener('fetch', event => {
  event.respondWith(handleRequest(event.request));
});

async function handleRequest(request) {
  if (request.method !== 'POST') {
    return new Response('Expected a POST request from Telegram.', { status: 405 });
  }

  const payload = await request.json();
  if (!payload.message) {
    return new Response('No message found.', { status: 200 });
  }

  const { chat, text } = payload.message;
  const chatId = chat.id;


  if (text === '/start') {
    const responseText = 'سلام! به ربات هواشناسی خوش آمدید. لطفا نام هر شهری را که می‌خواهید، وارد کنید.';
    await sendMessage(chatId, responseText);
    return new Response('OK');
  }

  const city = text;

  const weatherData = await getWeatherData(city);

  if (weatherData) {
    const tempCelsius = (weatherData.main.temp - 273.15).toFixed(1);
    const responseText = `✨ **پیش‌بینی آب و هوای ${city}:**\n` +
                         `🌡️ دما: ${tempCelsius}°C\n` +
                         `💧 رطوبت: ${weatherData.main.humidity}%\n` +
                         `💨 سرعت باد: ${weatherData.wind.speed} m/s\n` +
                         `☁️ وضعیت: ${weatherData.weather[0].description}`;
    await sendMessage(chatId, responseText);
  } else {
    const responseText = `❌ متأسفانه اطلاعاتی برای شهر ${city} یافت نشد.`;
    await sendMessage(chatId, responseText);
  }
  
  return new Response('OK');
}

async function getWeatherData(city) {

  const url = `http://api.openweathermap.org/data/2.5/weather?q=${city}&appid=${WEATHER_API_KEY}&lang=fa`;
  const response = await fetch(url);
  
  if (response.ok) {
    return await response.json();
  } else {
    console.error(`Error fetching weather for ${city}: ${response.statusText}`);
    return null;
  }
}

async function sendMessage(chatId, text) {
  const url = `https://api.telegram.org/bot${TELEGRAM_BOT_TOKEN}/sendMessage`;
  const data = {
    chat_id: chatId,
    text: text,
    parse_mode: 'Markdown'
  };

  await fetch(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify(data)
  });
}