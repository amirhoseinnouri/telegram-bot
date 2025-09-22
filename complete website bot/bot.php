<?php

$BOT_TOKEN   = getenv("BOT_TOKEN");
$API_BASE    = getenv("API_BASE_URL");
$KV_PATH     = getenv("KV_PATH") ?: __DIR__ . "/sessions"; 

if (!file_exists($KV_PATH)) {
    mkdir($KV_PATH, 0777, true);
}

$API_URL = "https://api.telegram.org/bot$BOT_TOKEN/";

function sendMessage($chat_id, $text) {
    global $API_URL;
    $url = $API_URL . "sendMessage";
    $data = ["chat_id" => $chat_id, "text" => $text];
    file_get_contents($url . "?" . http_build_query($data));
}

function kv_get($chat_id) {
    global $KV_PATH;
    $file = "$KV_PATH/$chat_id.json";
    if (!file_exists($file)) return null;
    return json_decode(file_get_contents($file), true);
}

function kv_put($chat_id, $data) {
    global $KV_PATH;
    $file = "$KV_PATH/$chat_id.json";
    file_put_contents($file, json_encode($data));
}

function kv_delete($chat_id) {
    global $KV_PATH;
    $file = "$KV_PATH/$chat_id.json";
    if (file_exists($file)) unlink($file);
}

// Masking helpers
function maskEmail($email) {
    if (!$email) return "";
    $parts = explode("@", $email);
    if (strlen($parts[0]) <= 2) return "***@" . $parts[1];
    return substr($parts[0], 0, 2) . "***@" . $parts[1];
}

function maskString($s, $keepStart = 2, $keepEnd = 0) {
    if (!$s) return "";
    $len = strlen($s);
    if ($len <= $keepStart + $keepEnd) return str_repeat("*", $len);
    return substr($s, 0, $keepStart) . str_repeat("*", $len - $keepStart - $keepEnd) . substr($s, -$keepEnd);
}

$update = json_decode(file_get_contents("php://input"), true);

if (!$update) exit;

$chat_id = $update["message"]["chat"]["id"];
$text    = trim($update["message"]["text"]);
$session = kv_get($chat_id) ?: [];

// START command
if ($text === "/start") {
    kv_delete($chat_id);
    kv_put($chat_id, ["step" => "coupon"]);
    sendMessage($chat_id, "👋 Welcome! Please enter your coupon code:");
    exit;
}

// Handle session steps
try {
    if (($session["step"] ?? "") === "coupon") {
        $session["coupon"] = $text;
        $session["step"]   = "email";
        sendMessage($chat_id, "📧 Please enter your email:");
    } elseif (($session["step"] ?? "") === "email") {
        $session["email"] = $text;
        $session["step"]  = "password";
        sendMessage($chat_id, "🔑 Please enter your password:");
    } elseif (($session["step"] ?? "") === "password") {
        $session["password"] = $text; // temp in memory only
        $session["step"]     = "name";
        sendMessage($chat_id, "👤 Please enter your full name:");
    } elseif (($session["step"] ?? "") === "name") {
        $session["name"] = $text;

        $payload = json_encode([
            "coupon"   => $session["coupon"],
            "email"    => $session["email"],
            "password" => $session["password"],
            "name"     => $session["name"]
        ]);

        $ch = curl_init("$API_BASE/api/v1/order");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        $resp = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($resp, true);

        if (!$result || !$result["success"]) {
            sendMessage($chat_id, "❌ Error creating order. Please try again.");
            kv_delete($chat_id);
            exit;
        }

        $session["orderId"] = $result["data"]["orderId"];
        $session["step"]    = "code";

        $safeSession = $session;
        unset($safeSession["password"]);
        kv_put($chat_id, $safeSession);

        sendMessage($chat_id, "📩 Order created. Please enter the 6-digit verification code:");
        exit;
    } elseif (($session["step"] ?? "") === "code") {
        $code = $text;

        $ch = curl_init("$API_BASE/api/v1/order/" . $session["orderId"] . "/code");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(["code" => $code]));
        $resp = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($resp, true);

        if (!$result || !$result["success"]) {
            sendMessage($chat_id, "⚠️ Invalid code. Please try again.");
            exit;
        }

        // Get status
        $ch = curl_init("$API_BASE/api/v1/order/" . $session["orderId"]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $resp = curl_exec($ch);
        curl_close($ch);

        $statusData = json_decode($resp, true);

        if (!$statusData || !$statusData["success"]) {
            sendMessage($chat_id, "⚠️ Error fetching status. Please try again later.");
            kv_delete($chat_id);
            exit;
        }

        $data = $statusData["data"];
        $resultMessage = "✅ Order Completed:\n\n";
        $resultMessage .= "📧 Email: " . maskEmail($data["email"]) . "\n";
        $resultMessage .= "🔑 Password: " . maskString($session["password"], 1, 1) . "\n";
        $resultMessage .= "👤 Name: " . maskString($data["name"], 2) . "\n";
        $resultMessage .= "🆔 Order ID: " . $data["orderId"] . "\n";
        $resultMessage .= "📦 Status: " . $data["status"] . "\n";

        sendMessage($chat_id, $resultMessage);
        kv_delete($chat_id);
        exit;
    } else {
        sendMessage($chat_id, "❓ Please start again with /start");
    }

    $safeSession = $session;
    unset($safeSession["password"]);
    kv_put($chat_id, $safeSession);

} catch (Exception $e) {
    sendMessage($chat_id, "⚠️ An internal error occurred. Please try again later.");
    kv_delete($chat_id);
}
