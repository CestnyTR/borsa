<?php

namespace App\MarketData\Support;

use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * HAREM decommissioned its HTTP polling endpoints (/tmp/altin.json and
 * /tmp/all_prices.json now return 404). Prices are only published over a
 * Socket.IO (Engine.IO v4) websocket. This reader opens a one-shot connection,
 * returns the first `price_changed` snapshot and closes — keeping the
 * synchronous contracts the surrounding pollers/scrapers rely on.
 */
class HaremSocketReader
{
    private const WS_HOST = 'hrmsocketonly.haremaltin.com';
    private const WS_PATH = '/socket.io/?EIO=4&transport=websocket';
    private const ORIGIN = 'https://canlipiyasalar.haremaltin.com';
    private const TIMEOUT = 15;

    // HAREM rate-limits rapid websocket connections (HTTP 429). Multiple
    // consumers (market poller + gold scrapers) each need a snapshot, so we
    // briefly share one fetch across all of them.
    private const CACHE_KEY = 'harem:socket:snapshot';
    private const CACHE_TTL = 3;

    // Reconnect if no frame (not even an Engine.IO ping) arrives for this long.
    private const IDLE_TIMEOUT = 45;

    /**
     * Return the latest price snapshot (a map of instrument code => row),
     * reusing a very recent fetch to avoid hammering the socket.
     *
     * @return array<string, mixed>
     */
    public function fetchSnapshot(): array
    {
        $cached = Cache::get(self::CACHE_KEY);
        if (is_array($cached) && $cached !== []) {
            return $cached;
        }

        $snapshot = $this->readSnapshot();
        Cache::put(self::CACHE_KEY, $snapshot, self::CACHE_TTL);

        return $snapshot;
    }

    /**
     * Open a persistent Socket.IO connection and invoke $onTick with every
     * price snapshot until $shouldStop() returns true or the connection drops
     * (in which case it throws, so the caller can reconnect).
     *
     * @param callable(array<string, mixed>): void $onTick
     * @param (callable(): bool)|null $shouldStop
     * @param (callable(): void)|null $onIdle called ~once/sec while no frame arrives
     */
    public function listen(callable $onTick, ?callable $shouldStop = null, ?callable $onIdle = null): void
    {
        $fp = $this->connect();

        try {
            $lastActivity = microtime(true);

            while (true) {
                if ($shouldStop !== null && $shouldStop()) {
                    return;
                }

                $read = [$fp];
                $write = null;
                $except = null;
                $ready = @stream_select($read, $write, $except, 1);

                if ($ready === false) {
                    // A signal (e.g. SIGTERM) can interrupt stream_select; let
                    // the caller decide whether that means "stop" or "error".
                    if ($shouldStop !== null && $shouldStop()) {
                        return;
                    }
                    throw new RuntimeException('stream_select failed');
                }

                if ($ready === 0) {
                    if ($onIdle !== null) {
                        $onIdle();
                    }
                    if (microtime(true) - $lastActivity > self::IDLE_TIMEOUT) {
                        throw new RuntimeException('Socket idle timeout — reconnecting');
                    }
                    continue;
                }

                $lastActivity = microtime(true);
                $data = $this->readEvent($fp);
                if ($data !== null) {
                    $onTick($data);
                }
            }
        } finally {
            fclose($fp);
        }
    }

    /**
     * Open a one-shot Socket.IO connection and return the first price snapshot,
     * a map of instrument code => row (alis/satis/...).
     *
     * @return array<string, mixed>
     */
    private function readSnapshot(): array
    {
        $fp = $this->connect();

        try {
            $deadline = microtime(true) + self::TIMEOUT;
            while (microtime(true) < $deadline) {
                $data = $this->readEvent($fp);
                if ($data !== null) {
                    return $data;
                }
            }

            throw new RuntimeException('No price_changed frame received within timeout');
        } finally {
            fclose($fp);
        }
    }

    /**
     * Open the TLS socket and perform the WebSocket upgrade handshake.
     *
     * @return resource
     */
    private function connect()
    {
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'SNI_enabled' => true,
            ],
        ]);

        $fp = @stream_socket_client(
            'ssl://' . self::WS_HOST . ':443',
            $errno,
            $errstr,
            self::TIMEOUT,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if ($fp === false) {
            throw new RuntimeException("Socket connect failed: {$errstr} ({$errno})");
        }

        stream_set_timeout($fp, self::TIMEOUT);
        $this->handshake($fp);

        return $fp;
    }

    /**
     * Read one text frame and, if it carries a price_changed event, return its
     * data map. Engine.IO open/ping control packets are answered transparently.
     *
     * @param resource $fp
     * @return array<string, mixed>|null
     */
    private function readEvent($fp): ?array
    {
        $message = $this->readTextFrame($fp);
        if ($message === null) {
            return null;
        }

        // Engine.IO packet type is the first char of the payload.
        switch ($message[0] ?? '') {
            case '0': // open -> connect to the default namespace
                $this->writeTextFrame($fp, '40');
                return null;
            case '2': // ping -> pong
                $this->writeTextFrame($fp, '3');
                return null;
            case '4': // Socket.IO message; "42[...]" == EVENT
                if (($message[1] ?? '') === '2') {
                    $payload = json_decode(substr($message, 2), true);
                    if (is_array($payload) && ($payload[0] ?? null) === 'price_changed') {
                        $data = $payload[1]['data'] ?? $payload[1] ?? null;
                        if (is_array($data) && $data !== []) {
                            return $data;
                        }
                    }
                }
                return null;
        }

        return null;
    }

    /**
     * Match a price row by its map key or its `code` field.
     *
     * @param array<string, mixed> $data
     * @param array<int, string> $keyCandidates
     * @param array<int, string> $codeCandidates
     * @return array<string, mixed>|null
     */
    public static function extractRow(array $data, array $keyCandidates, array $codeCandidates): ?array
    {
        $keyCandidates = array_map('strtoupper', $keyCandidates);
        $codeCandidates = array_map('strtoupper', $codeCandidates);

        foreach ($data as $key => $value) {
            if (!is_array($value)) {
                continue;
            }

            $normalizedKey = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) $key) ?? '');
            if ($normalizedKey !== '' && in_array($normalizedKey, $keyCandidates, true)) {
                return $value;
            }

            $code = strtoupper((string) ($value['code'] ?? $value['kod'] ?? ''));
            if ($code !== '' && in_array($code, $codeCandidates, true)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param resource $fp
     */
    private function handshake($fp): void
    {
        $key = base64_encode(random_bytes(16));
        $request = 'GET ' . self::WS_PATH . " HTTP/1.1\r\n"
            . 'Host: ' . self::WS_HOST . "\r\n"
            . "Upgrade: websocket\r\n"
            . "Connection: Upgrade\r\n"
            . "Sec-WebSocket-Key: {$key}\r\n"
            . "Sec-WebSocket-Version: 13\r\n"
            . 'Origin: ' . self::ORIGIN . "\r\n"
            . 'User-Agent: ' . config('app.scraper_user_agent', 'Mozilla/5.0') . "\r\n\r\n";

        if (fwrite($fp, $request) === false) {
            throw new RuntimeException('WebSocket handshake write failed');
        }

        $response = '';
        while (!str_contains($response, "\r\n\r\n")) {
            $chunk = fread($fp, 1024);
            if ($chunk === '' || $chunk === false) {
                if ($this->timedOut($fp)) {
                    throw new RuntimeException('WebSocket handshake timed out');
                }
                break;
            }
            $response .= $chunk;
        }

        if (!preg_match('#^HTTP/1\.[01] 101#', $response)) {
            $status = strtok($response, "\r\n") ?: 'unknown';
            throw new RuntimeException("WebSocket upgrade failed: {$status}");
        }
    }

    /**
     * Read a single text frame, replying to control frames transparently.
     * Returns null when a non-text/continuation frame is consumed.
     *
     * @param resource $fp
     */
    private function readTextFrame($fp): ?string
    {
        $header = $this->readExact($fp, 2);
        $b0 = ord($header[0]);
        $b1 = ord($header[1]);

        $opcode = $b0 & 0x0F;
        $masked = ($b1 & 0x80) !== 0;
        $len = $b1 & 0x7F;

        if ($len === 126) {
            $ext = $this->readExact($fp, 2);
            $len = (ord($ext[0]) << 8) | ord($ext[1]);
        } elseif ($len === 127) {
            $ext = $this->readExact($fp, 8);
            $len = 0;
            for ($i = 0; $i < 8; $i++) {
                $len = ($len << 8) | ord($ext[$i]);
            }
        }

        $maskKey = $masked ? $this->readExact($fp, 4) : '';
        $payload = $len > 0 ? $this->readExact($fp, $len) : '';

        if ($masked && $payload !== '') {
            $unmasked = '';
            for ($i = 0, $n = strlen($payload); $i < $n; $i++) {
                $unmasked .= $payload[$i] ^ $maskKey[$i % 4];
            }
            $payload = $unmasked;
        }

        switch ($opcode) {
            case 0x1: // text
                return $payload;
            case 0x8: // close
                throw new RuntimeException('WebSocket closed by server');
            case 0x9: // ping -> pong
                $this->writeFrame($fp, 0xA, $payload);
                return null;
            default: // pong / continuation / binary — ignore
                return null;
        }
    }

    /**
     * @param resource $fp
     */
    private function writeTextFrame($fp, string $payload): void
    {
        $this->writeFrame($fp, 0x1, $payload);
    }

    /**
     * @param resource $fp
     */
    private function writeFrame($fp, int $opcode, string $payload): void
    {
        $len = strlen($payload);
        $frame = chr(0x80 | $opcode); // FIN + opcode

        // Client frames must be masked.
        if ($len <= 125) {
            $frame .= chr(0x80 | $len);
        } elseif ($len <= 0xFFFF) {
            $frame .= chr(0x80 | 126) . pack('n', $len);
        } else {
            $frame .= chr(0x80 | 127) . pack('J', $len);
        }

        $mask = random_bytes(4);
        $frame .= $mask;
        for ($i = 0; $i < $len; $i++) {
            $frame .= $payload[$i] ^ $mask[$i % 4];
        }

        if (fwrite($fp, $frame) === false) {
            throw new RuntimeException('WebSocket frame write failed');
        }
    }

    /**
     * @param resource $fp
     */
    private function readExact($fp, int $length): string
    {
        $buffer = '';
        while (strlen($buffer) < $length) {
            $chunk = fread($fp, $length - strlen($buffer));
            if ($chunk === '' || $chunk === false) {
                if ($this->timedOut($fp)) {
                    throw new RuntimeException('WebSocket read timed out');
                }
                throw new RuntimeException('WebSocket connection closed while reading');
            }
            $buffer .= $chunk;
        }

        return $buffer;
    }

    /**
     * @param resource $fp
     */
    private function timedOut($fp): bool
    {
        $meta = stream_get_meta_data($fp);
        return (bool) ($meta['timed_out'] ?? false);
    }
}
