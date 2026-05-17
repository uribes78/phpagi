<?php

namespace Phpagi;

final readonly class CallerId
{
    public function __construct(
        public string $name = '',
        public string $protocol = '',
        public string $username = '',
        public string $host = '',
        public string $port = '',
    ) {}

    public static function parse(?string $callerId): self
    {
        if ($callerId === null || $callerId === '') {
            return new self();
        }

        $callerId = trim($callerId);
        $name = '';
        $protocol = '';
        $username = '';
        $host = '';
        $port = '';

        if ($callerId !== '' && ($callerId[0] === '"' || $callerId[0] === "'")) {
            $delim = $callerId[0];
            $parts = explode($delim, substr($callerId, 1));
            $name = array_shift($parts);
            $callerId = implode($delim, $parts);
        }

        $callerId = trim($callerId, '<> ');

        if (str_contains($callerId, '@')) {
            $atParts = explode('@', $callerId);
            $userPart = $atParts[0];
            $hostPart = $atParts[1] ?? '';

            if (str_contains($userPart, ':')) {
                $userParts = explode(':', $userPart);
                $protocol = $userParts[0];
                $username = $userParts[1] ?? '';
            } else {
                $username = $userPart;
            }

            if (str_contains($hostPart, ':')) {
                $hostParts = explode(':', $hostPart);
                $host = $hostParts[0];
                $port = $hostParts[1] ?? '';
            } else {
                $host = $hostPart;
            }
        } else {
            $username = $callerId;
        }

        return new self(
            name: $name,
            protocol: $protocol,
            username: $username,
            host: $host,
            port: $port,
        );
    }
}
