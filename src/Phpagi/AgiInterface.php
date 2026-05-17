<?php

namespace Phpagi;

interface AgiInterface
{
    public function answer(): AgiResponse;

    public function channelStatus(?string $channel = null): AgiResponse;

    public function databaseDel(string $family, string $key): AgiResponse;

    public function databaseDeltree(string $family, ?string $keytree = null): AgiResponse;

    public function databaseGet(string $family, string $key): AgiResponse;

    public function databasePut(string $family, string $key, string $value): AgiResponse;

    public function setGlobalVar(string $variable, string|int|float $value): AgiResponse;

    public function setVar(string $variable, string|int|float $value): AgiResponse;

    public function execApplication(string $application, string|array $options): AgiResponse;

    public function getData(string $filename, ?int $timeout = null, ?int $maxDigits = null): AgiResponse;

    public function getVariable(string $variable): ?string;

    public function getFullVariable(string $variable, ?string $channel = null): ?string;

    public function hangup(?string $channel = null): AgiResponse;

    public function noop(?string $message = null): AgiResponse;

    public function receiveChar(int $timeout = -1): AgiResponse;

    public function recordFile(
        string $file,
        string $format,
        string $escapeDigits = '',
        int $timeout = -1,
        ?int $offset = null,
        bool $beep = false,
        ?int $silence = null,
    ): AgiResponse;

    public function sayAlpha(string $text, string $escapeDigits = ''): AgiResponse;

    public function sayDigits(int $digits, string $escapeDigits = ''): AgiResponse;

    public function sayNumber(int $number, string $escapeDigits = ''): AgiResponse;

    public function sayPhonetic(string $text, string $escapeDigits = ''): AgiResponse;

    public function sayTime(?int $timestamp = null, string $escapeDigits = ''): AgiResponse;

    public function sendImage(string $image): AgiResponse;

    public function sendText(string $text): AgiResponse;

    public function setAutohangup(int $timeout = 0): AgiResponse;

    public function setCallerId(string $callerId): AgiResponse;

    public function setContext(string $context): AgiResponse;

    public function setExtension(string $extension): AgiResponse;

    public function setMusic(bool $enabled = true, string $class = ''): AgiResponse;

    public function setPriority(int $priority): AgiResponse;

    public function setVariable(string $variable, string $value): AgiResponse;

    public function streamFile(string $filename, string $escapeDigits = '', int $offset = 0): AgiResponse;

    public function tddMode(string $setting): AgiResponse;

    public function verbose(string $message, int $level = 1): AgiResponse;

    public function waitForDigit(int $timeout = -1): AgiResponse;
}
