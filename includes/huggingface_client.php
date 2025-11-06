<?php
declare(strict_types=1);

/**
 * Lightweight Hugging Face chat completion client.
 * Depends on includes/config.php to expose GG_HUGGINGFACE_TOKEN.
 */

require_once __DIR__ . '/config.php';

/**
 * Send chat-style messages to a Hugging Face Inference Provider endpoint.
 *
 * @param array<int, array{role:string, content:string}> $messages
 * @param string $model Fully qualified model identifier, e.g. meta-llama/Llama-3.1-8B-Instruct:novita
 * @param array<string,mixed> $options Optional extra payload values (e.g. temperature, max_tokens)
 * @return array<string,mixed>
 *
 * @throws RuntimeException when the token is missing or the request fails.
 */
function gg_hf_chat(array $messages, string $model, array $options = []): array
{
    if (empty(GG_HUGGINGFACE_TOKEN)) {
        throw new RuntimeException('Missing Hugging Face token. Set HUGGINGFACE_TOKEN in .env.');
    }

    $endpoint = $options['endpoint'] ?? 'https://router.huggingface.co/v1/chat/completions';

    $payload = array_merge(
        [
            'model' => $model,
            'messages' => $messages,
            'stream' => false,
        ],
        $options
    );

    unset($payload['endpoint']); // ensure endpoint is not sent in JSON

    $ch = curl_init($endpoint);
    if ($ch === false) {
        throw new RuntimeException('Unable to initialise cURL for Hugging Face request.');
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . GG_HUGGINGFACE_TOKEN,
            'Content-Type: application/json',
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT => (int)($options['timeout'] ?? 30),
    ]);

    $rawResponse = curl_exec($ch);
    if ($rawResponse === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('Hugging Face request failed: ' . $error);
    }

    $statusCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    $decoded = json_decode($rawResponse, true);
    if ($decoded === null) {
        throw new RuntimeException('Invalid JSON response from Hugging Face: ' . $rawResponse);
    }

    if ($statusCode >= 400) {
        $message = is_array($decoded) && isset($decoded['error'])
            ? $decoded['error']
            : $rawResponse;
        throw new RuntimeException('Hugging Face API error (' . $statusCode . '): ' . $message);
    }

    return $decoded;
}

