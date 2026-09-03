<?php

namespace App\Services;

class ImapClient
{
    private $conn = null;

    private int $tag = 0;

    public function __construct(private string $host, private int $port, private string $security)
    {
    }

    private function command(string $cmd): array
    {
        $this->tag++;
        $tag = 'A' . $this->tag;
        fwrite($this->conn, "$tag $cmd\r\n");

        $lines = [];
        while (! feof($this->conn)) {
            $line = fgets($this->conn);
            if ($line === false) {
                break;
            }
            $lines[] = rtrim($line, "\r\n");
            if (preg_match("/^$tag (OK|NO|BAD) /", $line)) {
                $status = trim(substr($line, strlen($tag) + 1));
                return [$status, $lines];
            }
        }
        throw new \RuntimeException('IMAP connection lost');
    }

    public function connect(): self
    {
        $security = strtolower($this->security);
        $errno = 0;
        $errstr = '';

        if ($security === 'ssl') {
            $this->conn = fsockopen("ssl://{$this->host}", $this->port, $errno, $errstr, 30);
        } else {
            $this->conn = fsockopen($this->host, $this->port, $errno, $errstr, 30);
        }
        if (! $this->conn) {
            throw new \RuntimeException("connect failed: $errstr ($errno)");
        }
        stream_set_timeout($this->conn, 30);

        $greeting = fgets($this->conn);
        if ($greeting === false || ! preg_match('/^\* OK/i', $greeting)) {
            throw new \RuntimeException('bad IMAP greeting');
        }

        if ($security === 'starttls') {
            [$status] = $this->command('STARTTLS');
            if (! str_starts_with($status, 'OK')) {
                throw new \RuntimeException('STARTTLS rejected');
            }
            $crypto = stream_socket_enable_crypto($this->conn, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT | STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if (! $crypto) {
                throw new \RuntimeException('TLS handshake failed');
            }
        }

        return $this;
    }

    public function login(string $username, string $password): void
    {
        [$status] = $this->command('LOGIN ' . $this->quote($username) . ' ' . $this->quote($password));
        if (! str_starts_with($status, 'OK')) {
            throw new \RuntimeException('IMAP login failed');
        }
    }

    public function listFolders(): array
    {
        [$status, $lines] = $this->command('LIST "" "*"');
        if (! str_starts_with($status, 'OK')) {
            throw new \RuntimeException('LIST failed');
        }
        $folders = [];
        foreach ($lines as $line) {
            // e.g. * LIST (\HasNoChildren) "/" "INBOX"
            if (preg_match('/^\* LIST \(.*?\) ".*?" "(.*)"$/', $line, $m)) {
                $folders[] = stripslashes($this->unescape($m[1]));
            }
        }
        return $folders;
    }

    public function fetchMessages(string $folder, int $limit): array
    {
        [$status] = $this->command('SELECT ' . $this->quote($folder));
        if (! str_starts_with($status, 'OK')) {
            throw new \RuntimeException("SELECT $folder failed");
        }

        [$status, $lines] = $this->command('STATUS ' . $this->quote($folder) . ' (MESSAGES)');
        $exists = 0;
        foreach ($lines as $line) {
            if (preg_match('/MESSAGES (\d+)/', $line, $m)) {
                $exists = (int) $m[1];
            }
        }
        if ($exists === 0) {
            return [];
        }

        $start = $exists > $limit ? $exists - $limit + 1 : 1;
        [$status, $lines] = $this->command("FETCH $start:$exists (UID RFC822.HEADER BODY.PEEK[TEXT])");
        if (! str_starts_with($status, 'OK')) {
            throw new \RuntimeException('FETCH failed');
        }

        return $this->parseFetches($lines);
    }

    private function parseFetches(array $lines): array
    {
        $messages = [];
        $current = null;
        $header = '';
        $text = '';
        $mode = 'idle';

        foreach ($lines as $line) {
            if (preg_match('/^\* (\d+) FETCH \(UID (\d+) RFC822\.HEADER \{(\d+)\}$/', $line, $m)) {
                $current = ['uid' => (int) $m[2]];
                $header = '';
                $text = '';
                $mode = 'header';
                continue;
            }
            if ($current === null) {
                continue;
            }
            if ($mode === 'header') {
                $header .= $line;  // line already has \r\n trimmed; re-add boundaries
                $header .= "\n";
                if (preg_match('/^\s*BODY\[TEXT\] \{(\d+)\}$/', $line, $m)) {
                    $mode = 'text';
                }
                continue;
            }
            if ($mode === 'text') {
                $text .= $line . "\n";
                if (preg_match('/\)$/i', $line)) {
                    $messages[] = MailParser::parse($current['uid'], $header, $text);
                    $current = null;
                    $mode = 'idle';
                }
            }
        }
        return $messages;
    }

    public function logout(): void
    {
        if ($this->conn) {
            @$this->command('LOGOUT');
            fclose($this->conn);
        }
    }

    private function quote(string $s): string
    {
        return '"' . addcslashes($s, "\\\"") . '"';
    }

    private function unescape(string $s): string
    {
        // decode UTF-7 like =?UTF-8?B?...?= is not used in LIST; handle \x escapes like Gmail's \x{...}
        return preg_replace_callback('/\\\\x\{([0-9A-Fa-f]+)\}/', function ($m) {
            return mb_chr((int) hexdec($m[1]));
        }, $s) ?? $s;
    }

    public function __destruct()
    {
        $this->logout();
    }
}