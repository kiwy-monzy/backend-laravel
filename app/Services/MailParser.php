<?php

namespace App\Services;

class MailParser
{
    public static function parse(int $uid, string $header, string $text): array
    {
        $parsed = self::parseHeaders($header);

        $subject = self::decodeHeader($parsed['subject'] ?? '');
        $from = self::decodeHeader($parsed['from'] ?? '');
        $to = self::decodeHeader($parsed['to'] ?? '');
        $date = $parsed['date'] ?? '';

        $preview = trim($text);
        if (str_starts_with(strtolower($preview), '<!doctype') || str_contains(strtolower(substr($preview, 0, 200)), '<html')) {
            $preview = strip_tags($preview);
        }
        $preview = preg_replace('/\s+/u', ' ', $preview) ?? '';

        return [
            'uid' => $uid,
            'subject' => $subject,
            'from' => $from,
            'to' => $to,
            'date' => $date,
            'body_preview' => mb_substr($preview, 0, 200),
            'read' => false,
        ];
    }

    private static function parseHeaders(string $raw): array
    {
        $headers = [];
        $lines = explode("\n", $raw);
        $name = null;
        $value = '';
        foreach ($lines as $line) {
            $line = rtrim($line, "\r");
            if (preg_match('/^([A-Za-z0-9\-]+):\s?(.*)$/', $line, $m)) {
                if ($name !== null) {
                    $headers[strtolower($name)] = $value;
                }
                $name = $m[1];
                $value = $m[2];
            } elseif ($name !== null && preg_match('/^\s+(.*)$/', $line, $m)) {
                $value .= ' ' . $m[1];
            }
        }
        if ($name !== null) {
            $headers[strtolower($name)] = $value;
        }
        return $headers;
    }

    private static function decodeHeader(string $value): string
    {
        // RFC 2047 encoded words: =?charset?B?base64?=  or  =?charset?Q?quoted?=
        return preg_replace_callback(
            '/=\?([^?]+)\?([BbQq])\?([^?]*)\?=/',
            function ($m) {
                $charset = strtolower($m[1]);
                $content = strtoupper($m[2]) === 'B'
                    ? base64_decode($m[3])
                    : quoted_printable_decode(str_replace('_', ' ', $m[3]));
                if (stripos($charset, 'utf-8') !== false || $charset === 'us-ascii') {
                    return $content;
                }
                $converted = mb_convert_encoding($content, 'UTF-8', $charset);
                return $converted === false ? $content : $converted;
            },
            $value
        ) ?? $value;
    }
}