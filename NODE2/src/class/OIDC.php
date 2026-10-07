<?php
// Client OIDC minimal untuk SSO Authentik (tanpa composer, hanya curl+openssl).
// Dipakai oleh: sso-login.php, oauth-callback.php, sso-logout.php.

class OIDC {
    const JWKS_TTL = 3600; // cache JWKS 1 jam di sys temp
    const LEeway   = 60;   // toleransi jam 60 detik untuk exp/iat/nbf

    // --- Konfigurasi dari environment (lihat docker-compose.yml) ---
    public static function issuer(): string {
        $v = getenv('AUTHENTIK_ISSUER');
        if (!$v && isset($_ENV['AUTHENTIK_ISSUER'])) $v = $_ENV['AUTHENTIK_ISSUER'];
        return rtrim(trim((string)($v ?: '')), '/') . '/';
    }

    public static function clientId(): string {
        $v = getenv('OIDC_CLIENT_ID');
        if (!$v && isset($_ENV['OIDC_CLIENT_ID'])) $v = $_ENV['OIDC_CLIENT_ID'];
        return trim((string)($v ?: ''));
    }

    private static function clientSecret(): string {
        $v = getenv('OIDC_CLIENT_SECRET');
        if (!$v && isset($_ENV['OIDC_CLIENT_SECRET'])) $v = $_ENV['OIDC_CLIENT_SECRET'];
        return (string)($v ?: '');
    }

    public static function appUrl(): string {
        $v = getenv('APP_URL');
        if (!$v && isset($_ENV['APP_URL'])) $v = $_ENV['APP_URL'];
        return rtrim(trim((string)($v ?: '')), '/');
    }

    // redirect_uri yang didaftarkan di Authentik (Application).
    public static function redirectUri(): string {
        return self::appUrl() . '/oauth-callback.php';
    }

    private static function requireConfigured(): void {
        if (self::issuer() === '/' || self::clientId() === '' || self::clientSecret() === '' || self::appUrl() === '') {
            throw new RuntimeException('Konfigurasi SSO belum lengkap (AUTHENTIK_ISSUER/OIDC_CLIENT_ID/OIDC_CLIENT_SECRET/APP_URL).');
        }
    }

    // Endpoint via OIDC Discovery (BAKU) — Authentik memakai path FLAT:
    // authorize+token di .../application/o/ TANPA slug, jwks per-slug.
    // Jangan menebak path: baca dari issuer/.well-known/openid-configuration.
    private static function discovery(): array {
        self::requireConfigured();
        $cache = sys_get_temp_dir() . '/psp_oidc_disc_' . md5(self::issuer()) . '.json';
        $raw = @file_get_contents($cache);
        if ($raw !== false) {
            $c = json_decode($raw, true);
            if (is_array($c) && isset($c['exp'], $c['doc']) && $c['exp'] > time() && !empty($c['doc']['issuer'])) {
                return $c['doc'];
            }
        }
        $url = self::issuer() . '.well-known/openid-configuration';
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $body = curl_exec($ch);
        $err  = curl_error($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if (!$err && $body !== false && $http >= 200 && $http < 300) {
            $doc = json_decode((string)$body, true);
            if (is_array($doc) && !empty($doc['issuer']) && !empty($doc['authorization_endpoint']) && !empty($doc['token_endpoint'])) {
                @file_put_contents($cache, json_encode(['exp' => time() + self::JWKS_TTL, 'doc' => $doc]), LOCK_EX);
                return $doc;
            }
        }
        if ($raw !== false) {
            $c = json_decode($raw, true);
            if (is_array($c) && !empty($c['doc']['authorization_endpoint'])) {
                error_log('[OIDC] discovery gagal, pakai cache basi.');
                return $c['doc'];
            }
        }
        error_log('[OIDC] discovery gagal: ' . ($err ?: ('HTTP ' . $http)));
        throw new RuntimeException('Verifikasi login SSO gagal.');
    }

    public static function authorizeEndpoint(): string { return (string)self::discovery()['authorization_endpoint']; }
    public static function tokenEndpoint(): string     { return (string)self::discovery()['token_endpoint']; }
    public static function jwksEndpoint(): string {
        $doc = self::discovery();
        return !empty($doc['jwks_uri']) ? (string)$doc['jwks_uri'] : (self::issuer() . 'jwks/');
    }
    public static function endSessionEndpoint(): string {
        $doc = self::discovery();
        return !empty($doc['end_session_endpoint']) ? (string)$doc['end_session_endpoint'] : (self::issuer() . 'end-session/');
    }

    // Bangun URL /authorize (state+nonce dibuat di sso-login.php, disimpan di session).
    public static function buildAuthorizeUrl(string $state, string $nonce): string {
        self::requireConfigured();
        $q = http_build_query([
            'client_id'     => self::clientId(),
            'redirect_uri'  => self::redirectUri(),
            'response_type' => 'code',
            'scope'         => 'openid profile email',
            'state'         => $state,
            'nonce'         => $nonce,
        ]);
        return self::authorizeEndpoint() . '?' . $q;
    }

    // Tukar code -> token (POST form). Return array hasil decode JSON.
    public static function exchangeCode(string $code): array {
        self::requireConfigured();
        $ch = curl_init(self::tokenEndpoint());
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        curl_setopt($ch, CURLOPT_USERPWD, self::clientId() . ':' . self::clientSecret());
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'grant_type'   => 'authorization_code',
            'code'         => $code,
            'redirect_uri' => self::redirectUri(),
        ]));
        $body = curl_exec($ch);
        $err  = curl_error($ch);
        $code_http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($err || $body === false) throw new RuntimeException('Gagal menghubungi Authentik (token).');
        $data = json_decode((string)$body, true);
        if ($code_http < 200 || $code_http >= 300 || !is_array($data) || empty($data['id_token'])) {
            error_log('[OIDC] token exchange gagal HTTP=' . $code_http);
            throw new RuntimeException('Verifikasi login SSO gagal.');
        }
        return $data;
    }

    // Ambil JWKS (cache file 1 jam). Return array JWKS.
    public static function fetchJwks(): array {
        self::requireConfigured();
        $cache = sys_get_temp_dir() . '/psp_jwks_' . md5(self::issuer()) . '.json';

        // 1) Pakai cache bila masih segar
        $raw = @file_get_contents($cache);
        if ($raw !== false) {
            $c = json_decode($raw, true);
            if (is_array($c) && isset($c['exp'], $c['jwks']) && $c['exp'] > time() && !empty($c['jwks']['keys'])) {
                return $c['jwks'];
            }
        }

        // 2) Fetch baru via curl
        $ch = curl_init(self::jwksEndpoint());
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $body = curl_exec($ch);
        $err  = curl_error($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!$err && $body !== false && $http >= 200 && $http < 300) {
            $jwks = json_decode((string)$body, true);
            if (is_array($jwks) && !empty($jwks['keys'])) {
                @file_put_contents($cache, json_encode(['exp' => time() + self::JWKS_TTL, 'jwks' => $jwks]), LOCK_EX);
                return $jwks;
            }
        }

        // 3) Fallback: cache basi masih boleh dipakai (lebih baik dari gagal total)
        if ($raw !== false) {
            $c = json_decode($raw, true);
            if (is_array($c) && !empty($c['jwks']['keys'])) {
                error_log('[OIDC] JWKS fetch gagal, pakai cache basi.');
                return $c['jwks'];
            }
        }

        error_log('[OIDC] JWKS tidak tersedia: ' . ($err ?: ('HTTP ' . $http)));
        throw new RuntimeException('Verifikasi login SSO gagal.');
    }

    // Verifikasi id_token. Tolak keras bila satu pun cek gagal. Return claims.
    public static function verifyIdToken(string $idToken, string $expectedNonce): array {
        self::requireConfigured();
        $parts = explode('.', $idToken);
        if (count($parts) !== 3) throw new RuntimeException('Verifikasi login SSO gagal.');

        $header  = json_decode(self::b64urlDecode($parts[0]), true);
        $payload = json_decode(self::b64urlDecode($parts[1]), true);
        $sig     = self::b64urlDecode($parts[2], true);
        if (!is_array($header) || !is_array($payload) || $sig === '') {
            throw new RuntimeException('Verifikasi login SSO gagal.');
        }

        // Algoritma hanya RS256, kid wajib ada
        if (($header['alg'] ?? '') !== 'RS256' || empty($header['kid'])) {
            throw new RuntimeException('Verifikasi login SSO gagal.');
        }

        // iss harus sama dengan AUTHENTIK_ISSUER (toleransi trailing slash)
        $iss = (string)($payload['iss'] ?? '');
        if ($iss === '' || !hash_equals(rtrim(self::issuer(), '/'), rtrim($iss, '/'))) {
            throw new RuntimeException('Verifikasi login SSO gagal.');
        }

        // aud harus client_id (string atau array)
        $aud = $payload['aud'] ?? null;
        $okAud = is_array($aud)
            ? in_array(self::clientId(), $aud, true)
            : (is_string($aud) && hash_equals(self::clientId(), $aud));
        if (!$okAud) throw new RuntimeException('Verifikasi login SSO gagal.');

        // exp wajib & belum lewat; iat/nbf bila ada dicek longgar
        $now = time();
        if (!isset($payload['exp']) || !is_numeric($payload['exp']) || ((int)$payload['exp'] + self::LEeway) < $now) {
            throw new RuntimeException('Verifikasi login SSO gagal.');
        }
        if (isset($payload['iat']) && is_numeric($payload['iat']) && ((int)$payload['iat'] - self::LEeway) > $now) {
            throw new RuntimeException('Verifikasi login SSO gagal.');
        }
        if (isset($payload['nbf']) && is_numeric($payload['nbf']) && ((int)$payload['nbf'] - self::LEeway) > $now) {
            throw new RuntimeException('Verifikasi login SSO gagal.');
        }

        // nonce wajib cocok (anti-replay)
        if (!isset($payload['nonce']) || !is_string($payload['nonce']) || !hash_equals($expectedNonce, $payload['nonce'])) {
            throw new RuntimeException('Verifikasi login SSO gagal.');
        }

        // sub wajib ada
        if (empty($payload['sub']) || !is_string($payload['sub'])) {
            throw new RuntimeException('Verifikasi login SSO gagal.');
        }

        // Tanda tangan RS256 via kunci kid dari JWKS
        $jwks = self::fetchJwks();
        $pem = '';
        foreach ($jwks['keys'] as $jwk) {
            if (($jwk['kty'] ?? '') === 'RSA' && ($jwk['kid'] ?? '') === $header['kid'] && !empty($jwk['n']) && !empty($jwk['e'])) {
                $pem = self::rsaJwkToPem($jwk['n'], $jwk['e']);
                break;
            }
        }
        if ($pem === '') throw new RuntimeException('Verifikasi login SSO gagal.');

        $signed = $parts[0] . '.' . $parts[1];
        $vr = openssl_verify($signed, $sig, $pem, OPENSSL_ALGO_SHA256);
        if ($vr !== 1) throw new RuntimeException('Verifikasi login SSO gagal.');

        return $payload;
    }

    // URL logout Authentik (end-session). post_logout_redirect HARUS salah satu
    // Logout URI terdaftar di provider (Authentik menolak URL lain) -> pakai
    // oauth-logout-callback.php yang memang redirect ke katalog.
    // id_token_hint (id_token sesi ini, dibaca SEBELUM destroy) mematikan sesi
    // SSO juga; tanpanya Authentik hanya tampil halaman konfirmasi + sesi hidup.
    public static function buildLogoutUrl(string $idTokenHint = ''): string {
        $base = self::appUrl() !== '' ? self::appUrl() : '';
        $back = $base !== '' ? $base . '/oauth-logout-callback.php' : 'oauth-logout-callback.php';
        $q = http_build_query([
            'post_logout_redirect_uri' => $back,
            'client_id'                => self::clientId(),
        ]);
        if ($idTokenHint !== '') {
            $q .= '&' . http_build_query(['id_token_hint' => $idTokenHint]);
        }
        return self::endSessionEndpoint() . '?' . $q;
    }

    // --- Helper internal ---

    private static function b64urlDecode(string $s, bool $raw = false) {
        $s = strtr($s, '-_', '+/');
        $pad = strlen($s) % 4;
        if ($pad) $s .= str_repeat('=', 4 - $pad);
        return $raw ? (string)base64_decode($s, true) : (string)base64_decode($s, true);
    }

    // JWK RSA (n,e base64url) -> PEM public key (ASN.1 DER).
    private static function rsaJwkToPem(string $n_b64, string $e_b64): string {
        $mod = base64_decode(strtr($n_b64, '-_', '+/'), true);
        $exp = base64_decode(strtr($e_b64, '-_', '+/'), true);
        if ($mod === false || $exp === false || $mod === '' || $exp === '') {
            throw new RuntimeException('Verifikasi login SSO gagal.');
        }
        $rsaSeq = self::der(0x30, self::derInt($mod) . self::derInt($exp));
        $oidRsa = hex2bin('300d06092a864886f70d0101010500'); // rsaEncryption + NULL
        $spki   = self::der(0x30, $oidRsa . self::der(0x03, "\x00" . $rsaSeq));
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($spki), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    private static function derLen(int $len): string {
        if ($len < 128) return chr($len);
        $b = ltrim(pack('N', $len), "\x00");
        return chr(0x80 | strlen($b)) . $b;
    }

    private static function derInt(string $bytes): string {
        $bytes = ltrim($bytes, "\x00");
        if ($bytes === '') $bytes = "\x00";
        if ((ord($bytes[0]) & 0x80) !== 0) $bytes = "\x00" . $bytes; // positif
        return chr(0x02) . self::derLen(strlen($bytes)) . $bytes;
    }

    private static function der(int $tag, string $content): string {
        return chr($tag) . self::derLen(strlen($content)) . $content;
    }
}
