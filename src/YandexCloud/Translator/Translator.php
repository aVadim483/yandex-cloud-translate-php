<?php

namespace avadim\YandexCloud\Translator;

use avadim\YandexCloud\Auth\Auth;

class Translator
{
    public array $urls = [];
    public bool $saveLog = false;

    private Auth $auth;
    private string $folderId;

    private float $lastRequestTime = 0.0;
    private int $symbolsCount = 0;
    private int $requestsCount = 0;
    private array $log = [];


    public function __construct($auth, $folderId)
    {
        $this->auth = $auth;
        $this->folderId = $folderId;
        $this->urls = [
            'detect' => 'https://translate.api.cloud.yandex.net/translate/v2/detect',
            'languages' => 'https://translate.api.cloud.yandex.net/translate/v2/languages',
            'translate' => 'https://translate.api.cloud.yandex.net/translate/v2/translate',
        ];
    }

    /**
     * @return string|null
     */
    protected function getFolderId(): ?string
    {
        return $this->folderId;
    }

    /**
     * Delay in order not to exceed the limit on the frequency of requests
     *
     * @return void
     */
    private function checkRequestsLimit()
    {
        $now = microtime(true);
        if ($this->lastRequestTime > 0) {
            $nextTime = $this->lastRequestTime + 0.1;
            $delta = $nextTime - $now;
            if ($delta > 0) {
                usleep($delta * 1000000);
            }
        }
        $this->lastRequestTime = $now;
    }

    /**
     * @return string[]
     */
    protected function getHeaders(): array
    {
        $token = $this->auth->getIamToken();
        return [
            'Content-Type: application/json',
            'X-Client-Request-ID: ' . Auth::makeUuid(),
            'Authorization: Bearer ' . $token,
        ];
    }

    /**
     * @param string $url
     * @param array $postData
     * @param string $result
     *
     * @return array|string
     */
    protected function request(string $url, array $postData, string $result)
    {
        $headers = $this->getHeaders();

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        // curl_setopt($curl, CURLOPT_VERBOSE, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POST, true);

        self::checkRequestsLimit();
        $this->addToLog([
            'time' => date('Y-m-d H:i:s'),
            'url' => $url,
            'headers' => $headers,
            'params' => $postData,
        ]);
        $time = microtime(true);
        $response = curl_exec($curl);
        $this->addToLog(['response_time' => microtime(true) - $time, 'response' => $response]);

        curl_close($curl);
        $this->requestsCount++;

        if (!empty($response) && $resultData = json_decode($response, true)) {
            if (!empty($resultData['error']['message'])) {
                throw new \RuntimeException('YC translate error: ' . $resultData['error']['message']);
            }

            if (!empty($resultData[$result])) {
                return $resultData[$result];
            }
        }

        throw new \RuntimeException('YC translate error (empty result)');
    }

    /**
     * @param string $text
     * @param array|null $languageCodeHints
     *
     * @return string
     */
    public function detectLanguage(string $text, ?array $languageCodeHints = []): string
    {
        $postData = [
            'folderId' => $this->getFolderId(),
            'text' => $text,
        ];
        if ($languageCodeHints) {
            $postData['languageCodeHints'] = $languageCodeHints;
        }
        $this->symbolsCount += mb_strlen($text);

        return $this->request($this->urls['detect'], $postData, 'languageCode');
    }

    /**
     * @return array
     */
    public function listLanguages(): array
    {
        $postData = [
            'folderId' => $this->getFolderId(),
        ];

        return $this->request($this->urls['languages'], $postData, 'languages');
    }

    /**
     * @param string|string[] $texts
     * @param string $targetLanguageCode
     * @param string|null $sourceLanguageCode
     * @param bool|null $htmlText
     *
     * @return array
     */
    public function translate($texts, string $targetLanguageCode, ?string $sourceLanguageCode = null, ?bool $htmlText = false): array
    {
        $postData = [
            'folderId' => $this->getFolderId(),
            'texts' => !is_array($texts) ? [(string)$texts] : $texts,
        ];
        if ($targetLanguageCode) {
            $postData['targetLanguageCode'] = $targetLanguageCode;
        }
        if ($targetLanguageCode) {
            $postData['sourceLanguageCode'] = $sourceLanguageCode;
        }
        if ($htmlText) {
            $postData['format'] = 'HTML';
        }
        foreach($texts as $text) {
            $this->symbolsCount += mb_strlen($text);
        }

        return $this->request($this->urls['translate'], $postData, 'translations');
    }

    /**
     * @return array
     */
    public function getStats(): array
    {
        return [
            'requests' => $this->requestsCount,
            'symbols' => $this->symbolsCount,
        ];
    }

    /**
     * @param array $data
     *
     * @return void
     */
    public function addToLog(array $data)
    {
        if ($this->saveLog) {
            $this->log[] = $data;
        }
    }

    /**
     * @param array $data
     *
     * @return void
     */
    public function appendToLog(array $data)
    {
        if ($this->saveLog) {
            $lastPos = count($this->log) - 1;
            foreach ($data as $key => $val) {
                $this->log[$lastPos][$key] = $val;
            }
        }
    }

    /**
     * @return array
     */
    public function getLog(): array
    {
        return $this->log;
    }
}