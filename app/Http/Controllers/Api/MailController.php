<?php

namespace App\Http\Controllers\Api;

use App\Models\MailConfig;
use App\Services\ImapClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PHPMailer\PHPMailer\PHPMailer;

class MailController extends ApiController
{
    public function saveConfig(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $data = $this->body($request);

        $config = MailConfig::firstOrNew(['user_id' => $user->id]);
        $config->fill([
            'email' => $data['email'] ?? '',
            'username' => $data['username'] ?? '',
            'password' => $data['password'] ?? '',
            'incoming_host' => $data['incoming_host'] ?? '',
            'incoming_port' => (int) ($data['incoming_port'] ?? 993),
            'incoming_protocol' => $data['incoming_protocol'] ?? 'imap',
            'incoming_security' => $data['incoming_security'] ?? 'ssl',
            'outgoing_host' => $data['outgoing_host'] ?? '',
            'outgoing_port' => (int) ($data['outgoing_port'] ?? 465),
            'outgoing_security' => $data['outgoing_security'] ?? 'ssl',
            'linked_at' => now()->toRfc3339String(),
        ]);
        $config->save();

        $testError = $this->testIncomingConnection($config);

        return $this->json([
            'success' => true,
            'test_success' => $testError === null,
            'test_error' => $testError,
        ]);
    }

    public function getConfig(Request $request): JsonResponse
    {
        $config = MailConfig::where('user_id', $this->user($request)->id)->first();
        if (! $config) {
            return $this->json(null);
        }

        return $this->json($config->toApi());
    }

    public function deleteConfig(Request $request): JsonResponse
    {
        MailConfig::where('user_id', $this->user($request)->id)->delete();

        return $this->ok();
    }

    public function fetchMails(Request $request): JsonResponse
    {
        $data = $this->body($request);
        $config = $this->requireConfig($request);
        if ($config instanceof JsonResponse) {
            return $config;
        }

        $alias = $data['folder'] ?? 'inbox';
        $limit = (int) ($data['limit'] ?? 50);

        if (strtolower($config->incoming_protocol) === 'pop3') {
            return $this->fail('POP3 fetching is not yet implemented. Please use IMAP.', 400);
        }

        try {
            $client = (new ImapClient($config->incoming_host, (int) $config->incoming_port, $config->incoming_security))
                ->connect();
            $client->login($config->username, $config->password);
            $folders = $client->listFolders();
            $realFolder = $this->resolveFolder($folders, $alias);
            $messages = $client->fetchMessages($realFolder, $limit);
        } catch (\Throwable $e) {
            return $this->fail('IMAP error: ' . $e->getMessage(), 500);
        }

        return $this->json(['messages' => $messages, 'folder' => $realFolder]);
    }

    public function listFolders(Request $request): JsonResponse
    {
        $config = $this->requireConfig($request);
        if ($config instanceof JsonResponse) {
            return $config;
        }

        if (strtolower($config->incoming_protocol) === 'pop3') {
            return $this->fail('POP3 folder listing is not yet implemented. Please use IMAP.', 400);
        }

        try {
            $client = (new ImapClient($config->incoming_host, (int) $config->incoming_port, $config->incoming_security))
                ->connect();
            $client->login($config->username, $config->password);
            $folders = $client->listFolders();
        } catch (\Throwable $e) {
            return $this->fail('IMAP folder list error: ' . $e->getMessage(), 500);
        }

        return $this->json(['folders' => $folders]);
    }

    public function sendMail(Request $request): JsonResponse
    {
        $data = $this->body($request);
        $config = $this->requireConfig($request);
        if ($config instanceof JsonResponse) {
            return $config;
        }

        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $config->outgoing_host;
            $mail->Port = (int) $config->outgoing_port;
            $mail->SMTPAuth = true;
            $mail->Username = $config->username;
            $mail->Password = $config->password;
            $mail->SMTPSecure = match (strtolower($config->outgoing_security)) {
                'starttls' => PHPMailer::ENCRYPTION_STARTTLS,
                'none' => '',
                default => PHPMailer::ENCRYPTION_SMTPS,
            };
            $mail->CharSet = PHPMailer::CHARSET_UTF8;
            $mail->setFrom($config->email, $config->username);
            $mail->addAddress($data['to'] ?? '');
            $mail->Subject = $data['subject'] ?? '';
            $mail->Body = $data['body'] ?? '';
            $mail->send();
        } catch (\Throwable $e) {
            return $this->fail('SMTP error: ' . $e->getMessage(), 500);
        }

        return $this->ok();
    }

    private function requireConfig(Request $request): MailConfig|JsonResponse
    {
        $config = MailConfig::where('user_id', $this->user($request)->id)->first();
        if (! $config) {
            return $this->fail('No mail config found', 400);
        }
        return $config;
    }

    private function testIncomingConnection(MailConfig $config): ?string
    {
        try {
            if (strtolower($config->incoming_protocol) === 'pop3') {
                return 'POP3 is not yet implemented. Please use IMAP.';
            }
            $client = (new ImapClient($config->incoming_host, (int) $config->incoming_port, $config->incoming_security))
                ->connect();
            $client->login($config->username, $config->password);
            $client->fetchMessages('INBOX', 1);
            return null;
        } catch (\Throwable $e) {
            return $e->getMessage();
        }
    }

    private function resolveFolder(array $folders, string $alias): string
    {
        $aliasLower = strtolower($alias);

        foreach ($folders as $f) {
            if (strtolower($f) === $aliasLower) {
                return $f;
            }
        }

        $candidates = match ($aliasLower) {
            'inbox' => ['INBOX'],
            'sent' => ['Sent', 'INBOX.Sent', 'Sent Items', 'INBOX.Sent Items'],
            'drafts' => ['Drafts', 'INBOX.Drafts', 'Draft'],
            'trash' => ['Trash', 'INBOX.Trash', 'Deleted Items', 'INBOX.Deleted Items', 'Deleted Messages'],
            'spam', 'junk' => ['Spam', 'INBOX.Spam', 'Junk', 'INBOX.Junk', 'Junk E-mail'],
            'starred', 'flagged' => ['Starred', 'INBOX.Starred', 'Flagged', 'INBOX.Flagged'],
            'archive' => ['Archive', 'INBOX.Archive', 'Archives'],
            default => [],
        };

        foreach ($candidates as $candidate) {
            foreach ($folders as $f) {
                if (strcasecmp($f, $candidate) === 0) {
                    return $f;
                }
            }
        }

        if (str_contains($aliasLower, '.') || ctype_upper($alias[0] ?? '')) {
            return $alias;
        }

        $prefixed = 'INBOX.' . $alias;
        foreach ($folders as $f) {
            if (strcasecmp($f, $prefixed) === 0) {
                return $prefixed;
            }
        }

        return 'INBOX';
    }
}