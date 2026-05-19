<?php

namespace Phpagi;

final readonly class AgiRequest
{
    private const EXPECTED_KEYS = [
        'agi_request', 'agi_channel', 'agi_language', 'agi_type',
        'agi_uniqueid', 'agi_callerid', 'agi_dnid', 'agi_rdnis',
        'agi_context', 'agi_extension', 'agi_priority', 'agi_enhanced',
        'agi_accountcode', 'agi_network', 'agi_network_script', 'agi_threadid',
    ];

    public string $request;
    public string $channel;
    public string $language;
    public string $type;
    public string $uniqueId;
    public string $callerId;
    public string $dnid;
    public string $rdnis;
    public string $context;
    public string $extension;
    public int $priority;
    public bool $enhanced;
    public string $accountCode;
    public bool $network;
    public string $networkScript;
    public string $threadid;
    public array $raw;

    public function __construct(array $variables)
    {
        $this->raw = $variables;
        $this->request = $variables['agi_request'] ?? '';
        $this->channel = $variables['agi_channel'] ?? '';
        $this->language = $variables['agi_language'] ?? '';
        $this->type = $variables['agi_type'] ?? '';
        $this->uniqueId = $variables['agi_uniqueid'] ?? '';
        $this->callerId = $variables['agi_callerid'] ?? '';
        $this->dnid = $variables['agi_dnid'] ?? '';
        $this->rdnis = $variables['agi_rdnis'] ?? '';
        $this->context = $variables['agi_context'] ?? '';
        $this->extension = $variables['agi_extension'] ?? '';
        $this->priority = (int) ($variables['agi_priority'] ?? 0);
        $this->enhanced = ($variables['agi_enhanced'] ?? '') === '1.0';
        $this->accountCode = $variables['agi_accountcode'] ?? '';
        $this->network = ($variables['agi_network'] ?? '') === 'yes';
        $this->networkScript = $variables['agi_network_script'] ?? '';
        $this->threadid = $variables['agi_threadid'] ?? '';
    }

    public static function fromStream($stream): self
    {
        $variables = [];
        $line = fgets($stream);

        while ($line !== false && $line !== "\n") {
            $colonPos = strpos($line, ':');
            if ($colonPos !== false) {
                $key = substr($line, 0, $colonPos);
                $variables[$key] = trim(substr($line, $colonPos + 1));
            }
            $line = fgets($stream);
        }

        return new self($variables);
    }
}
