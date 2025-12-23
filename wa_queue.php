<?php
// File untuk menyimpan antrian pesan WA yang gagal
define('WA_QUEUE_FILE', __DIR__ . '/wa_queue.json');

function getWaQueue() {
    if (file_exists(WA_QUEUE_FILE)) {
        $content = file_get_contents(WA_QUEUE_FILE);
        $data = json_decode($content, true);
        if (is_array($data)) return $data;
    }
    return array();
}

function saveWaQueue($data) {
    file_put_contents(WA_QUEUE_FILE, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);
}

function addToWaQueue($phone, $message, $type, $userid, $tanggal) {
    $queue = getWaQueue();
    $queue[] = array(
        'phone' => $phone,
        'message' => $message,
        'type' => $type,
        'userid' => $userid,
        'tanggal' => $tanggal,
        'created_at' => date('Y-m-d H:i:s'),
        'retry_count' => 0
    );
    saveWaQueue($queue);
}

function removeFromQueue($index) {
    $queue = getWaQueue();
    if (isset($queue[$index])) {
        unset($queue[$index]);
        $queue = array_values($queue); // Re-index array
        saveWaQueue($queue);
    }
}

function updateRetryCount($index) {
    $queue = getWaQueue();
    if (isset($queue[$index])) {
        $queue[$index]['retry_count']++;
        $queue[$index]['last_retry'] = date('Y-m-d H:i:s');
        saveWaQueue($queue);
    }
}
