<?php
/**
 * Web Push murni PHP (tanpa Composer): VAPID (RFC 8292) dan enkripsi pesan aes128gcm (RFC 8291).
 * Butuh ekstensi openssl dan curl. Kunci VAPID disimpan di environment variable Vercel:
 *   VAPID_PUBLIC_KEY, VAPID_PRIVATE_KEY (base64url), VAPID_SUBJECT (mailto:... atau https://...)
 */

function b64u_encode(string $bin): string
{
    return rtrim(strtr(base64_encode($bin), "+/", "-_"), "=");
}

function b64u_decode(string $s): string
{
    return (string) base64_decode(strtr($s, "-_", "+/") . str_repeat("=", (4 - strlen($s) % 4) % 4), true);
}

function push_enabled(): bool
{
    return function_exists("openssl_pkey_derive") && function_exists("curl_init")
        && (string) env_value("VAPID_PUBLIC_KEY", "") !== "" && (string) env_value("VAPID_PRIVATE_KEY", "") !== "";
}

/** Pasangan kunci VAPID baru: ["public" => ..., "private" => ...] (base64url). */
function vapid_generate(): ?array
{
    $key = openssl_pkey_new(["curve_name" => "prime256v1", "private_key_type" => OPENSSL_KEYTYPE_EC]);
    if ($key === false) {
        return null;
    }
    $d = openssl_pkey_get_details($key)["ec"] ?? null;
    if (!$d) {
        return null;
    }
    $pub = "\x04" . str_pad($d["x"], 32, "\0", STR_PAD_LEFT) . str_pad($d["y"], 32, "\0", STR_PAD_LEFT);
    return ["public" => b64u_encode($pub), "private" => b64u_encode(str_pad($d["d"], 32, "\0", STR_PAD_LEFT))];
}

/** Kunci privat EC (resource OpenSSL) dari 32 byte mentah + titik publik 65 byte. */
function push_private_key(string $d, string $pub)
{
    $der = "\x30\x77\x02\x01\x01\x04\x20" . $d
         . "\xa0\x0a\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07"
         . "\xa1\x44\x03\x42\x00" . $pub;
    $pem = "-----BEGIN EC PRIVATE KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END EC PRIVATE KEY-----\n";
    return openssl_pkey_get_private($pem);
}

/** Kunci publik EC (resource OpenSSL) dari titik 65 byte. */
function push_public_key(string $pub)
{
    $der = hex2bin("3059301306072a8648ce3d020106082a8648ce3d030107034200") . $pub;
    $pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
    return openssl_pkey_get_public($pem);
}

/** Tanda tangan ES256 dalam format mentah r||s (64 byte). */
function push_sign_es256(string $data, $privateKey): ?string
{
    if (!openssl_sign($data, $der, $privateKey, OPENSSL_ALGO_SHA256)) {
        return null;
    }
    // DER: 30 len 02 lenR R 02 lenS S
    $off = 2 + ((ord($der[1]) & 0x80) ? (ord($der[1]) & 0x7f) : 0);
    $rl = ord($der[$off + 1]);
    $r = substr($der, $off + 2, $rl);
    $sl = ord($der[$off + 3 + $rl]);
    $s = substr($der, $off + 4 + $rl, $sl);
    $r = str_pad(ltrim($r, "\0"), 32, "\0", STR_PAD_LEFT);
    $s = str_pad(ltrim($s, "\0"), 32, "\0", STR_PAD_LEFT);
    return $r . $s;
}

function vapid_authorization(string $endpoint): ?string
{
    $pubB64 = (string) env_value("VAPID_PUBLIC_KEY", "");
    $d = b64u_decode((string) env_value("VAPID_PRIVATE_KEY", ""));
    $pub = b64u_decode($pubB64);
    if (strlen($d) !== 32 || strlen($pub) !== 65) {
        return null;
    }
    $parts = parse_url($endpoint);
    if (empty($parts["scheme"]) || empty($parts["host"])) {
        return null;
    }
    $aud = $parts["scheme"] . "://" . $parts["host"] . (isset($parts["port"]) ? ":" . $parts["port"] : "");
    $subject = (string) env_value("VAPID_SUBJECT", "mailto:admin@moodjourney.app");
    $head = b64u_encode(json_encode(["typ" => "JWT", "alg" => "ES256"]));
    $claims = b64u_encode(json_encode(["aud" => $aud, "exp" => time() + 12 * 3600, "sub" => $subject]));
    $key = push_private_key($d, $pub);
    if ($key === false) {
        return null;
    }
    $sig = push_sign_es256($head . "." . $claims, $key);
    if ($sig === null) {
        return null;
    }
    return "vapid t=" . $head . "." . $claims . "." . b64u_encode($sig) . ", k=" . $pubB64;
}

/**
 * Enkripsi aes128gcm (RFC 8291). Parameter $asPrivate, $asPublic, $salt hanya untuk pengujian.
 * @return string badan permintaan (header + ciphertext) atau "" kalau gagal
 */
function push_encrypt(string $payload, string $uaPublic, string $authSecret, ?string $asPrivate = null, ?string $asPublic = null, ?string $salt = null): string
{
    if (strlen($uaPublic) !== 65 || strlen($authSecret) < 8) {
        return "";
    }
    if ($asPrivate === null) {
        $gen = vapid_generate();
        if (!$gen) {
            return "";
        }
        $asPrivate = b64u_decode($gen["private"]);
        $asPublic = b64u_decode($gen["public"]);
    }
    $salt = $salt ?? random_bytes(16);

    $priv = push_private_key($asPrivate, (string) $asPublic);
    $peer = push_public_key($uaPublic);
    if ($priv === false || $peer === false) {
        return "";
    }
    $secret = openssl_pkey_derive($peer, $priv, 32);
    if ($secret === false) {
        return "";
    }

    $ikm = hash_hkdf("sha256", $secret, 32, "WebPush: info\0" . $uaPublic . $asPublic, $authSecret);
    $cek = hash_hkdf("sha256", $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
    $nonce = hash_hkdf("sha256", $ikm, 12, "Content-Encoding: nonce\0", $salt);

    $plain = $payload . "\x02"; // satu rekaman saja, pembatas akhir
    $tag = "";
    $cipher = openssl_encrypt($plain, "aes-128-gcm", $cek, OPENSSL_RAW_DATA, $nonce, $tag, "", 16);
    if ($cipher === false) {
        return "";
    }
    return $salt . pack("N", 4096) . chr(65) . $asPublic . $cipher . $tag;
}

/**
 * Kirim satu notifikasi. @return int kode HTTP (0 = gagal sebelum terkirim)
 */
function push_send(array $sub, array $message): int
{
    if (!push_enabled()) {
        return 0;
    }
    $body = push_encrypt(
        json_encode($message, JSON_UNESCAPED_UNICODE),
        b64u_decode((string) $sub["p256dh"]),
        b64u_decode((string) $sub["auth"])
    );
    $auth = vapid_authorization((string) $sub["endpoint"]);
    if ($body === "" || $auth === null) {
        return 0;
    }
    $ch = curl_init((string) $sub["endpoint"]);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_HTTPHEADER     => [
            "Content-Type: application/octet-stream",
            "Content-Encoding: aes128gcm",
            "TTL: 43200",
            "Urgency: normal",
            "Authorization: " . $auth,
        ],
    ]);
    curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $status;
}

/** Kirim ke semua perangkat milik satu pengguna; langganan yang sudah mati dihapus. @return int jumlah terkirim */
function push_send_user(int $userId, array $message): int
{
    $sent = 0;
    foreach (db_all("SELECT id, endpoint, p256dh, auth FROM push_subscriptions WHERE user_id = ?", "i", [$userId]) as $sub) {
        $code = push_send($sub, $message);
        if ($code >= 200 && $code < 300) {
            $sent++;
        } elseif ($code === 404 || $code === 410) {
            db_exec("DELETE FROM push_subscriptions WHERE id = ?", "i", [(int) $sub["id"]]);
        }
    }
    return $sent;
}
