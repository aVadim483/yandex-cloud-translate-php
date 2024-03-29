<?php

namespace avadim\YandexCloud\Auth;

class Auth
{
    public string $refreshTokenUrl = 'https://iam.api.cloud.yandex.net/iam/v1/tokens';

    private ?string $oAuthToken;
    private array $tmpIamToken;

    private $cacheGetFunc = null;
    private $cachePutFunc = null;

    private ?string $apiKey = null;


    public function __construct($oAuthToken, $cacheGetFunc = null, $cachePutFunc = null)
    {
        $this->oAuthToken = $oAuthToken;
        if ($cacheGetFunc) {
            $this->cacheGetFunc = $cacheGetFunc;
        }
        if ($cachePutFunc) {
            $this->cachePutFunc = $cachePutFunc;
        }
    }

    public function setApiKey($apiKey)
    {
        $this->apiKey = $apiKey;
    }

    public function getApiKey(): ?string
    {
        return $this->apiKey;
    }

    /**
     * @return array
     */
    public function refreshIamToken(): array
    {
        $headers = [];

        $data = [
            'yandexPassportOauthToken' => $this->oAuthToken,
        ];

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($curl, CURLOPT_URL, $this->refreshTokenUrl);
        curl_setopt($curl, CURLOPT_POST, true);

        $response = curl_exec($curl);

        if ($response && $result = json_decode($response, true)) {

            return $result;
        }

        throw new \RuntimeException('Cannot refresh YC IAM token');
    }

    /**
     * @return string|null
     */
    public function getIamToken(): ?string
    {
        $token = !empty($this->tmpIamToken) ? $this->tmpIamToken : [];
        if ((empty($token['iamToken']) || ($token['timeExpiresAt'] > time())) && $this->cacheGetFunc) {
            $token = ($this->cacheGetFunc)();
        }
        if (empty($token['iamToken']) || ($token['timeExpiresAt'] > time())) {
            $token = $this->refreshIamToken();

            if (isset($token['code'], $token['message']) && $token['code'] === 16) {
                throw new \RuntimeException($token['message']);
            }
            if ($token) {
                if (preg_match('/^(.+)T(.+)\.(\d+)/', $token['expiresAt'], $m)) {
                    $d = \DateTime::createFromFormat('Y-m-d H:i:s.u', $m[1] . ' ' . $m[2] . '.' . substr($m[3], 0, 6));
                }
                else {
                    $d = \DateTime::createFromFormat('Y-m-d H:i:s.u', $token['expiresAt']);
                }
                if (!$d) {
                    $d = new \DateTime();
                    $d->setTimestamp(time() + 3600);
                }
                $token['timeExpiresAt'] = $d->getTimestamp();

                $ttl = $token['timeExpiresAt'] - time();
                if ($this->cachePutFunc) {
                    ($this->cachePutFunc)($token, $ttl);
                }
                $this->tmpIamToken = $token;

                return $token['iamToken'] ?? null;
            }
        }

        throw new \RuntimeException('Cannot refresh YC IAM token');
    }

    /**
     *  Generates RFC 4122 compliant Version 4 UUIDs
     *
     * @return string
     */
    public static function makeUuid(): string
    {
        // Generate 16 bytes (128 bits) of random data
        try {
            $data = random_bytes(16);
        }
        catch (\Exception $e) {
            $data = substr(sha1(uniqid('', true)), 0, 16);
        }

        // Set version to 0100
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        // Set bits 6-7 to 10
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        // Output the 36 character UUID.
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}