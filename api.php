<?php
// অপ্রয়োজনীয় এরর বন্ধ রাখা
error_reporting(0);
ini_set('display_errors', 0);

// হেডার কনফিগারেশন
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

$action = $_GET['action'] ?? '';
$roomId = preg_replace('/[^a-zA-Z0-9]/', '', $_GET['room'] ?? '');

if (!$roomId) {
    echo json_encode(['success' => false, 'error' => 'রুম কোড দেওয়া হয়নি']);
    exit;
}

$roomFile = __DIR__ . '/room_' . $roomId . '.json';

// নিরাপদ ফাইল লেখার ফাংশন (লক কাজ না করলে সাধারণ রাইট করবে)
function safeFileWrite($filePath, $data) {
    $fp = @fopen($filePath, 'w');
    if ($fp) {
        @flock($fp, LOCK_EX); // লক করার চেষ্টা করবে
        fwrite($fp, $data);
        fflush($fp);
        @flock($fp, LOCK_UN);
        fclose($fp);
        return true;
    }
    // fopen ফেইল করলে file_put_contents চেষ্টা করবে
    return @file_put_contents($filePath, $data) !== false;
}

// ১. নতুন রুম তৈরি
if ($action === 'create') {
    $rawInput = file_get_contents('php://input');
    $decoded = json_decode($rawInput, true);

    if (!$decoded) {
        echo json_encode(['success' => false, 'error' => 'অবৈধ ডেটা পাঠানো হয়েছে']);
        exit;
    }

    if (safeFileWrite($roomFile, json_encode($decoded, JSON_UNESCAPED_UNICODE))) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'সার্ভারে ফাইল সেভ করার পারমিশন নেই']);
    }
    exit;
}

// ২. রুমে নিরাপদে জয়েন করা
if ($action === 'join') {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    $playerName = trim($input['name'] ?? 'খেলোয়াড়');

    if (!file_exists($roomFile)) {
        echo json_encode(['success' => false, 'error' => 'রুমটি পাওয়া যায়নি']);
        exit;
    }

    $content = @file_get_contents($roomFile);
    $state = json_decode($content, true);

    if (!$state) {
        echo json_encode(['success' => false, 'error' => 'রুমের ডেটা পড়া যাচ্ছে না']);
        exit;
    }

    if ($state['gameStarted']) {
        echo json_encode(['success' => false, 'error' => 'খেলা ইতিমধ্যে শুরু হয়ে গেছে']);
        exit;
    }

    if (count($state['players']) >= 4) {
        echo json_encode(['success' => false, 'error' => 'রুমটি পূর্ণ (সর্বোচ্চ ৪ জন)']);
        exit;
    }

    $newPlayerIndex = count($state['players']);
    $state['players'][] = [
        'id' => $newPlayerIndex,
        'name' => $playerName,
        'money' => 2500,
        'position' => 0,
        'properties' => [],
        'inJail' => false,
        'jailTurns' => 0,
        'bankrupt' => false
    ];
    $state['version'] = ($state['version'] ?? 1) + 1;

    if (safeFileWrite($roomFile, json_encode($state, JSON_UNESCAPED_UNICODE))) {
        echo json_encode(['success' => true, 'playerIndex' => $newPlayerIndex, 'state' => $state]);
    } else {
        echo json_encode(['success' => false, 'error' => 'জয়েন সেভ করা যায়নি']);
    }
    exit;
}

// ৩. গেম স্টেট আপডেট
if ($action === 'update') {
    $rawInput = file_get_contents('php://input');
    $decoded = json_decode($rawInput, true);

    if (!$decoded) {
        echo json_encode(['success' => false, 'error' => 'অবৈধ ডেটা']);
        exit;
    }

    if (safeFileWrite($roomFile, json_encode($decoded, JSON_UNESCAPED_UNICODE))) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'স্টেট আপডেট করা যায়নি']);
    }
    exit;
}

// ৪. গেমের তথ্য পড়া (Fast Polling)
if ($action === 'get') {
    if (!file_exists($roomFile)) {
        echo json_encode(['success' => false, 'error' => 'রুম পাওয়া যায়নি']);
        exit;
    }

    $data = @file_get_contents($roomFile);
    echo $data ? $data : json_encode(['success' => false, 'error' => 'ডেটা ফাঁকা']);
    exit;
}

echo json_encode(['success' => false, 'error' => 'অবৈধ অ্যাকশন']);