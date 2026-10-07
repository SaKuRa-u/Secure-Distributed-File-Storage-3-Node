<?php
// SCIM client minimal untuk provisioning pegawai via Authentik SCIM source.
// Base URL + token dari environment (lihat docker-compose.yml):
//   AUTHENTIK_SCIM_URL         cth https://auth.example.com/source/scim/<slug>/v2
//   AUTHENTIK_SCIM_TOKEN       Bearer token khusus SCIM source (JANGAN commit)
//   AUTHENTIK_PEGAWAI_GROUP_ID UUID grup psp-pegawai (disalin dari URL grup di Authentik)
//
// Aturan keras: JANGAN log token / Authorization header / full request body.
// Ke server log hanya HTTP code + pesan generik. Ke admin hanya pesan generik.
// Respons SCIM exact Authentik belum terverifikasi -> parse defensif:
// terima 200/201/204, parse JSON toleran, anggap gagal bila tak dikenali.
class ScimClient {
    const TIMEOUT = 15;

    public static function baseUrl(): string {
        $v = getenv('AUTHENTIK_SCIM_URL');
        if (!$v && isset($_ENV['AUTHENTIK_SCIM_URL'])) $v = $_ENV['AUTHENTIK_SCIM_URL'];
        return rtrim(trim((string)($v ?: '')), '/');
    }

    private static function token(): string {
        $v = getenv('AUTHENTIK_SCIM_TOKEN');
        if (!$v && isset($_ENV['AUTHENTIK_SCIM_TOKEN'])) $v = $_ENV['AUTHENTIK_SCIM_TOKEN'];
        return trim((string)($v ?: ''));
    }

    public static function pegawaiGroupId(): string {
        $v = getenv('AUTHENTIK_PEGAWAI_GROUP_ID');
        if (!$v && isset($_ENV['AUTHENTIK_PEGAWAI_GROUP_ID'])) $v = $_ENV['AUTHENTIK_PEGAWAI_GROUP_ID'];
        return trim((string)($v ?: ''));
    }

    public static function isConfigured(): bool {
        $base = self::baseUrl();
        if ($base === '' || self::token() === '') return false;
        // Fail closed: hanya http(s) URL, tolak string aneh.
        if (!preg_match('#^https?://#i', $base)) return false;
        return true;
    }

    /**
     * POST /Users. SENGAJA TANPA password: terbukti live (test-login gagal)
     * bahwa SCIM Authentik mengabaikan field password. Password di-set via
     * Authentik UI (Reset password) atau recovery flow. JANGAN tambah param
     * password di sini tanpa bukti live bahwa ia tersimpan.
     * $groupId ikut dikirim (standar SCIM) tapi DIVERIFIKASI via sisi grup.
     * Return: ['ok'=>bool,'id'=>?string,'http'=>int,'conflict'=>bool]
     */
    public static function createUser(string $username, string $nama, string $email, string $groupId = ''): array {
        $result = ['ok' => false, 'id' => null, 'http' => 0, 'conflict' => false];
        if (!self::isConfigured()) {
            error_log('[SCIM] create user ditolak: konfigurasi SCIM belum lengkap.');
            return $result;
        }
        $payload = [
            'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:User'],
            'userName' => $username,
            'displayName' => $nama,
            'name' => ['formatted' => $nama, 'givenName' => $nama],
            'emails' => [['value' => $email, 'primary' => true]],
            'active' => true,
        ];
        if ($groupId !== '') {
            $payload['groups'] = [['value' => $groupId]];
        }
        $res = self::request('POST', self::baseUrl() . '/Users', $payload);
        $result['http'] = $res['http'];
        if ($res['http'] === 409) {
            $result['conflict'] = true;
            error_log('[SCIM] create user conflict HTTP=409.');
            return $result;
        }
        // Beberapa implementasi pakai 400 + pesan uniqueness -> perlakukan sebagai conflict.
        if ($res['http'] === 400 && self::looksLikeConflict($res['body'])) {
            $result['conflict'] = true;
            error_log('[SCIM] create user conflict (400 uniqueness).');
            return $result;
        }
        if (!in_array($res['http'], [200, 201], true)) {
            error_log('[SCIM] create user gagal HTTP=' . $res['http']);
            return $result;
        }
        $id = self::extractId($res['json']);
        if (!is_string($id) || $id === '') {
            error_log('[SCIM] create user gagal: respons tak dikenali HTTP=' . $res['http']);
            return $result;
        }
        $result['ok'] = true;
        $result['id'] = $id;
        return $result;
    }

    /**
     * Adopsi grup via POST /Groups (TERVERIFIKASI dari source resmi:
     * update_group mencari Group.objects by NAME -> grup manual ikut ter-link
     * ke source + members langsung dipasang). Dipakai karena PATCH /Groups/{id}
     * 404 untuk grup yang belum ter-link SCIM.
     * Return: ['ok'=>bool,'http'=>int]
     */
    public static function adoptGroupWithMember(string $groupName, string $scimUserId, string $displayName = ''): array {
        $out = ['ok' => false, 'http' => 0];
        if (!self::isConfigured() || $groupName === '' || $scimUserId === '') {
            error_log('[SCIM] adopt-group ditolak: konfigurasi/parameter belum lengkap.');
            return $out;
        }
        $member = ['value' => $scimUserId];
        if ($displayName !== '') $member['display'] = $displayName;
        $payload = [
            'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:Group'],
            'displayName' => $groupName,
            'members' => [$member],
        ];
        $res = self::request('POST', self::baseUrl() . '/Groups', $payload);
        $out['http'] = $res['http'];
        if (in_array($res['http'], [200, 201], true)) {
            $out['ok'] = true;
            return $out;
        }
        error_log('[SCIM] adopt-group gagal HTTP=' . $res['http']);
        return $out;
    }

    /**
     * Tambah user ke grup psp-pegawai (RFC SCIM PATCH /Groups/{id}).
     * CATATAN: 404 bila grup belum ter-link ke SCIM source -> pakai
     * adoptGroupWithMember() dulu. Return: ['ok'=>bool,'http'=>int]
     */
    public static function addUserToGroup(string $scimUserId, string $displayName = ''): array {
        $out = ['ok' => false, 'http' => 0];
        $groupId = self::pegawaiGroupId();
        if (!self::isConfigured() || $groupId === '' || $scimUserId === '') {
            error_log('[SCIM] add-to-group ditolak: konfigurasi/parameter belum lengkap.');
            return $out;
        }
        $member = ['value' => $scimUserId];
        if ($displayName !== '') $member['display'] = $displayName;
        $payload = [
            'schemas' => ['urn:ietf:params:scim:api:messages:2.0:PatchOp'],
            'Operations' => [[
                'op' => 'add',
                'path' => 'members',
                'value' => [$member],
            ]],
        ];
        $res = self::request('PATCH', self::baseUrl() . '/Groups/' . rawurlencode($groupId), $payload);
        $out['http'] = $res['http'];
        if (in_array($res['http'], [200, 201, 204], true)) {
            $out['ok'] = true;
            return $out;
        }
        error_log('[SCIM] add-to-group gagal HTTP=' . $res['http']);
        return $out;
    }

    // --- Helper internal ---

    /**
     * Cek keberadaan user di Authentik via SCIM GET /Users/{id}.
     * Return: true = masih ada; false = 404 sudah dihapus;
     *         null = tak dapat dipastikan (gangguan) -> pemanggil fail closed.
     */
    public static function userExists(string $scimUserId): ?bool {
        if (!self::isConfigured() || $scimUserId === '') return null;
        $ch = curl_init(self::baseUrl() . '/Users/' . rawurlencode($scimUserId));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, self::TIMEOUT);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/scim+json',
            'Authorization: Bearer ' . self::token(),
        ]);
        $resp = curl_exec($ch);
        $err  = curl_error($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($err || $resp === false) {
            error_log('[SCIM] cek user gagal (network).');
            return null;
        }
        if ($http === 404) return false;
        if ($http >= 200 && $http < 300) return true;
        error_log('[SCIM] cek user gagal HTTP=' . $http);
        return null;
    }

    /**
     * Verifikasi keanggotaan dari SISI GRUP (ground truth): GET /Groups/{uuid}
     * lalu cocokkan members[].value dengan UUID user. WAJIB dipakai, karena
     * GET /Users menggemakan kembali payload create (termasuk groups) TANPA
     * validasi -> cek sisi user memberi false-positive (insiden Otto).
     */
    public static function groupHasMember(string $groupUuid, string $scimUserId): bool {
        if (!self::isConfigured() || $groupUuid === '' || $scimUserId === '') return false;
        $ch = curl_init(self::baseUrl() . '/Groups/' . rawurlencode($groupUuid));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, self::TIMEOUT);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/scim+json',
            'Authorization: Bearer ' . self::token(),
        ]);
        $resp = curl_exec($ch);
        $err  = curl_error($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($err || $resp === false || $http < 200 || $http >= 300) {
            error_log('[SCIM] verifikasi grup gagal HTTP=' . $http);
            return false;
        }
        $doc = json_decode((string)$resp, true);
        if (!is_array($doc) || empty($doc['members']) || !is_array($doc['members'])) return false;
        foreach ($doc['members'] as $m) {
            if (!is_array($m)) continue;
            if (isset($m['value']) && strcasecmp((string)$m['value'], $scimUserId) === 0) return true;
        }
        return false;
    }

    /**
     * Verifikasi keanggotaan: GET /Users/{id}, cocokkan groups[] by value
     * (UUID) ATAU display/name (nama grup). Return true bila cocok.
     */
    public static function userInGroup(string $scimUserId, string $groupId): bool {
        if (!self::isConfigured() || $scimUserId === '' || $groupId === '') return false;
        $ch = curl_init(self::baseUrl() . '/Users/' . rawurlencode($scimUserId));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, self::TIMEOUT);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/scim+json',
            'Authorization: Bearer ' . self::token(),
        ]);
        $resp = curl_exec($ch);
        $err  = curl_error($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($err || $resp === false || $http < 200 || $http >= 300) {
            error_log('[SCIM] verifikasi grup gagal HTTP=' . $http);
            return false;
        }
        $doc = json_decode((string)$resp, true);
        if (!is_array($doc) || empty($doc['groups']) || !is_array($doc['groups'])) return false;
        foreach ($doc['groups'] as $g) {
            if (!is_array($g)) continue;
            foreach (['value', 'display', 'name', '$ref'] as $k) {
                if (isset($g[$k]) && stripos((string)$g[$k], $groupId) !== false) return true;
            }
            if (isset($g['display']) && strcasecmp((string)$g['display'], 'psp-pegawai') === 0) return true;
        }
        return false;
    }

    private static function request(string $method, string $url, array $payload): array {
        $body = json_encode($payload);
        if (!is_string($body)) $body = '{}';
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, self::TIMEOUT);
        // JANGAN log $body penuh / token. Header hanya di memori curl.
        // SCIM RFC 7644 mewajibkan media type scim+json; application/json
        // ditolak Authentik dengan 406. Berlaku untuk create + group.
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/scim+json',
            'Content-Type: application/scim+json',
            'Authorization: Bearer ' . self::token(),
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        $resp = curl_exec($ch);
        $err = curl_error($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($err || $resp === false) {
            error_log('[SCIM] request curl gagal.');
            return ['http' => 0, 'body' => '', 'json' => null];
        }
        $json = json_decode((string)$resp, true);
        return ['http' => $http, 'body' => (string)$resp, 'json' => is_array($json) ? $json : null];
    }

    private static function looksLikeConflict(string $body): bool {
        if ($body === '') return false;
        $s = strtolower(substr($body, 0, 2000));
        foreach (['conflict', 'uniqu', 'already', 'exists', 'taken', 'duplicate'] as $k) {
            if (str_contains($s, $k)) return true;
        }
        return false;
    }

    private static function extractId($json): ?string {
        if (!is_array($json)) return null;
        if (isset($json['id']) && is_string($json['id']) && $json['id'] !== '') return $json['id'];
        // Toleran: beberapa server membungkus di Resources[].
        if (isset($json['Resources']) && is_array($json['Resources'])) {
            $first = $json['Resources'][0] ?? null;
            if (is_array($first) && isset($first['id']) && is_string($first['id']) && $first['id'] !== '') {
                return $first['id'];
            }
        }
        return null;
    }
}
