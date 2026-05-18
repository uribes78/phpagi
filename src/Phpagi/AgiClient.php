<?php

namespace Phpagi;

use Phpagi\Exception\AgiException;
use Phpagi\Exception\ConnectionException;

final class AgiClient implements AgiInterface
{
    public const AGIRES_OK = 200;
    public const DEFAULT_OPTION_DELIM = ',';

    private const MAX_READ_ATTEMPTS = 5;
    private const MONTH_SECONDS = 2592000;

    private mixed $stdin;
    private mixed $stdout;
    private mixed $audio = null;

    private readonly AgiRequest $request;

    private array $config = [];

    public function __construct(
        ?array $config = null,
        ?AgiRequest $request = null,
        mixed $stdin = null,
        mixed $stdout = null,
    ) {
        $this->config = $config ?? $this->loadConfig();

        $this->config['phpagi']['error_handler'] ??= true;
        $this->config['phpagi']['debug'] ??= false;
        $this->config['phpagi']['admin'] ??= null;
        $this->config['phpagi']['tempdir'] ??= '/var/spool/asterisk/tmp/';
        $this->config['festival']['text2wave'] ??= $this->which('text2wave');
        $this->config['cepstral']['swift'] ??= $this->which('swift');

        $this->stdin = $stdin ?? (defined('STDIN') ? STDIN : fopen('php://stdin', 'r'));
        $this->stdout = $stdout ?? (defined('STDOUT') ? STDOUT : fopen('php://stdout', 'w'));

        if ($this->stdin === false || $this->stdout === false) {
            throw new ConnectionException('Failed to open standard I/O streams');
        }

        if ($this->config['phpagi']['error_handler'] === true) {
            set_error_handler(self::errorHandler(...));
            error_reporting(E_ALL);
        }

        $this->makeTempDir($this->config['phpagi']['tempdir']);

        $this->request = $request ?? AgiRequest::fromStream($this->stdin);

        if ($this->request->enhanced) {
            $this->openAudioStream();
        }

        $this->log('AGI Request: ' . print_r($this->request->raw, true));
    }

    // -----------------------------------------------------------------------
    //  Request
    // -----------------------------------------------------------------------

    public function getRequest(): AgiRequest
    {
        return $this->request;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    // -----------------------------------------------------------------------
    //  AGI Commands
    // -----------------------------------------------------------------------

    public function answer(): AgiResponse
    {
        return $this->evaluate('ANSWER');
    }

    public function channelStatus(?string $channel = null): AgiResponse
    {
        return $this->evaluate('CHANNEL STATUS ' . ($channel ?? ''));
    }

    public function databaseDel(string $family, string $key): AgiResponse
    {
        return $this->evaluate("DATABASE DEL \"{$family}\" \"{$key}\"");
    }

    public function databaseDeltree(string $family, ?string $keytree = null): AgiResponse
    {
        $cmd = "DATABASE DELTREE \"{$family}\"";
        if ($keytree !== null && $keytree !== '') {
            $cmd .= " \"{$keytree}\"";
        }
        return $this->evaluate($cmd);
    }

    public function databaseGet(string $family, string $key): AgiResponse
    {
        return $this->evaluate("DATABASE GET \"{$family}\" \"{$key}\"");
    }

    public function databasePut(string $family, string $key, string $value): AgiResponse
    {
        $value = str_replace("\n", '\n', addslashes($value));
        return $this->evaluate("DATABASE PUT \"{$family}\" \"{$key}\" \"{$value}\"");
    }

    public function setGlobalVar(string $variable, string|int|float $value): AgiResponse
    {
        $val = is_numeric($value) ? (string) $value : "\"{$value}\"";
        return $this->evaluate("Set({$variable}={$val},g);");
    }

    public function setVar(string $variable, string|int|float $value): AgiResponse
    {
        $val = is_numeric($value) ? (string) $value : "\"{$value}\"";
        return $this->evaluate("Set({$variable}={$val});");
    }

    public function execApplication(string $application, string|array $options): AgiResponse
    {
        if (is_array($options)) {
            $options = implode('|', $options);
        }
        return $this->evaluate("EXEC {$application} {$options}");
    }

    public function getData(string $filename, ?int $timeout = null, ?int $maxDigits = null): AgiResponse
    {
        $cmd = rtrim("GET DATA {$filename} {$timeout} {$maxDigits}");
        return $this->evaluate($cmd);
    }

    public function getVariable(string $variable): ?string
    {
        $response = $this->evaluate("GET VARIABLE {$variable}");
        return $response->result === 1 ? $response->data : null;
    }

    public function getFullVariable(string $variable, ?string $channel = null): ?string
    {
        $req = $variable;
        if ($channel !== null) {
            $req .= ' ' . $channel;
        }
        $response = $this->evaluate("GET FULL VARIABLE {$req}");
        return $response->result === 1 ? $response->data : null;
    }

    public function hangup(?string $channel = null): AgiResponse
    {
        return $this->evaluate('HANGUP ' . ($channel ?? ''));
    }

    public function noop(?string $message = null): AgiResponse
    {
        return $this->evaluate('NOOP "' . ($message ?? '') . '"');
    }

    public function receiveChar(int $timeout = -1): AgiResponse
    {
        return $this->evaluate("RECEIVE CHAR {$timeout}");
    }

    public function recordFile(
        string $file,
        string $format,
        string $escapeDigits = '',
        int $timeout = -1,
        ?int $offset = null,
        bool $beep = false,
        ?int $silence = null,
    ): AgiResponse {
        $cmd = trim("RECORD FILE {$file} {$format} \"{$escapeDigits}\" {$timeout} {$offset}");
        if ($beep) {
            $cmd .= ' BEEP';
        }
        if ($silence !== null) {
            $cmd .= " s={$silence}";
        }
        return $this->evaluate($cmd);
    }

    public function sayAlpha(string $text, string $escapeDigits = ''): AgiResponse
    {
        return $this->evaluate("SAY ALPHA {$text} \"{$escapeDigits}\"");
    }

    public function sayDigits(int $digits, string $escapeDigits = ''): AgiResponse
    {
        return $this->evaluate("SAY DIGITS {$digits} \"{$escapeDigits}\"");
    }

    public function sayNumber(int $number, string $escapeDigits = ''): AgiResponse
    {
        return $this->evaluate("SAY NUMBER {$number} \"{$escapeDigits}\"");
    }

    public function sayPhonetic(string $text, string $escapeDigits = ''): AgiResponse
    {
        return $this->evaluate("SAY PHONETIC {$text} \"{$escapeDigits}\"");
    }

    public function sayTime(?int $timestamp = null, string $escapeDigits = ''): AgiResponse
    {
        $timestamp ??= time();
        return $this->evaluate("SAY TIME {$timestamp} \"{$escapeDigits}\"");
    }

    public function sendImage(string $image): AgiResponse
    {
        return $this->evaluate("SEND IMAGE {$image}");
    }

    public function sendText(string $text): AgiResponse
    {
        return $this->evaluate("SEND TEXT \"{$text}\"");
    }

    public function setAutohangup(int $timeout = 0): AgiResponse
    {
        return $this->evaluate("SET AUTOHANGUP {$timeout}");
    }

    public function setCallerId(string $callerId): AgiResponse
    {
        return $this->evaluate("SET CALLERID {$callerId}");
    }

    public function setContext(string $context): AgiResponse
    {
        return $this->evaluate("SET CONTEXT {$context}");
    }

    public function setExtension(string $extension): AgiResponse
    {
        return $this->evaluate("SET EXTENSION {$extension}");
    }

    public function setMusic(bool $enabled = true, string $class = ''): AgiResponse
    {
        $state = $enabled ? 'ON' : 'OFF';
        return $this->evaluate("SET MUSIC {$state} {$class}");
    }

    public function setPriority(int $priority): AgiResponse
    {
        return $this->evaluate("SET PRIORITY {$priority}");
    }

    public function setVariable(string $variable, string $value): AgiResponse
    {
        $value = str_replace("\n", '\n', addslashes($value));
        return $this->evaluate("SET VARIABLE {$variable} \"{$value}\"");
    }

    public function streamFile(string $filename, string $escapeDigits = '', int $offset = 0): AgiResponse
    {
        return $this->evaluate("STREAM FILE {$filename} \"{$escapeDigits}\" {$offset}");
    }

    public function tddMode(string $setting): AgiResponse
    {
        return $this->evaluate("TDD MODE {$setting}");
    }

    public function verbose(string $message, int $level = 1): AgiResponse
    {
        $last = new AgiResponse();
        foreach (explode("\n", str_replace("\r\n", "\n", $message)) as $line) {
            @syslog(LOG_WARNING, $line);
            $last = $this->evaluate("VERBOSE \"{$line}\" {$level}");
        }
        return $last;
    }

    public function waitForDigit(int $timeout = -1): AgiResponse
    {
        return $this->evaluate("WAIT FOR DIGIT {$timeout}");
    }

    // -----------------------------------------------------------------------
    //  Application Helpers
    // -----------------------------------------------------------------------

    public function execAbsoluteTimeout(int $seconds = 0): AgiResponse
    {
        return $this->execApplication('AbsoluteTimeout', (string) $seconds);
    }

    public function execAgi(string $command, string $args): AgiResponse
    {
        return $this->execApplication("AGI {$command}", $args);
    }

    public function execSetLanguage(string $language = 'en'): AgiResponse
    {
        return $this->execApplication('Set', 'CHANNEL(language)=' . $language);
    }

    public function execEnumLookup(string $exten): AgiResponse
    {
        return $this->execApplication('EnumLookup', $exten);
    }

    public function execDial(
        string $type,
        string $identifier,
        ?int $timeout = null,
        ?string $options = null,
        ?string $url = null,
    ): AgiResponse {
        $delim = self::DEFAULT_OPTION_DELIM;
        $args = trim(
            "{$type}/{$identifier}{$delim}{$timeout}{$delim}{$options}{$delim}{$url}",
            $delim
        );
        return $this->execApplication('Dial', $args);
    }

    public function execGoto(string $context, ?string $extension = null, ?string $priority = null): AgiResponse
    {
        $delim = self::DEFAULT_OPTION_DELIM;
        $args = trim(
            "{$context}{$delim}{$extension}{$delim}{$priority}",
            $delim
        );
        return $this->execApplication('Goto', $args);
    }

    public function setContextExtensionPriority(
        string $context,
        string $extension = 's',
        int $priority = 1,
    ): void {
        $this->setContext($context);
        $this->setExtension($extension);
        $this->setPriority($priority);
    }

    // -----------------------------------------------------------------------
    //  Text-to-Speech
    // -----------------------------------------------------------------------

    public function text2wav(string $text, string $escapeDigits = '', int $frequency = 8000): AgiResponse|true
    {
        $text = trim($text);
        if ($text === '') {
            return true;
        }

        $hash = md5($text);
        $base = $this->config['phpagi']['tempdir'] . DIRECTORY_SEPARATOR . "text2wav_{$hash}";

        if (!file_exists("{$base}.wav")) {
            if (!file_exists("{$base}.txt")) {
                file_put_contents("{$base}.txt", $text);
            }
            $binary = $this->config['festival']['text2wave'] ?? 'text2wave';
            shell_exec("{$binary} -F {$frequency} -o {$base}.wav {$base}.txt");
        } else {
            touch("{$base}.txt");
            touch("{$base}.wav");
        }

        $response = $this->streamFile($base, $escapeDigits);

        $expire = time() - self::MONTH_SECONDS;
        foreach (glob($this->config['phpagi']['tempdir'] . DIRECTORY_SEPARATOR . 'text2wav_*') as $file) {
            if (filemtime($file) < $expire) {
                unlink($file);
            }
        }

        return $response;
    }

    public function swift(string $text, string $escapeDigits = '', int $frequency = 8000, ?string $voice = null): AgiResponse|true
    {
        $voiceOpt = '';
        if ($voice !== null) {
            $voiceOpt = "-n {$voice}";
        } elseif (isset($this->config['cepstral']['voice'])) {
            $voiceOpt = "-n {$this->config['cepstral']['voice']}";
        }

        $text = trim($text);
        if ($text === '') {
            return true;
        }

        $hash = md5($text);
        $base = $this->config['phpagi']['tempdir'] . DIRECTORY_SEPARATOR . "swift_{$hash}";

        if (!file_exists("{$base}.wav")) {
            if (!file_exists("{$base}.txt")) {
                file_put_contents("{$base}.txt", $text);
            }
            $binary = $this->config['cepstral']['swift'] ?? 'swift';
            shell_exec(
                "{$binary} -p audio/channels=1,audio/sampling-rate={$frequency} {$voiceOpt} -o {$base}.wav -f {$base}.txt"
            );
        }

        $response = $this->streamFile($base, $escapeDigits);

        $expire = time() - self::MONTH_SECONDS;
        foreach (glob($this->config['phpagi']['tempdir'] . DIRECTORY_SEPARATOR . 'swift_*') as $file) {
            if (filemtime($file) < $expire) {
                unlink($file);
            }
        }

        return $response;
    }

    // -----------------------------------------------------------------------
    //  DTMF Accumulation (FastPass)
    // -----------------------------------------------------------------------

    public function sayDigitsCollect(string &$buffer, int $digits, string $escapeDigits = ''): AgiResponse
    {
        return $this->fastPass(fn () => $this->sayDigits($digits, $escapeDigits), $buffer, $escapeDigits);
    }

    public function sayNumberCollect(string &$buffer, int $number, string $escapeDigits = ''): AgiResponse
    {
        return $this->fastPass(fn () => $this->sayNumber($number, $escapeDigits), $buffer, $escapeDigits);
    }

    public function sayPhoneticCollect(string &$buffer, string $text, string $escapeDigits = ''): AgiResponse
    {
        return $this->fastPass(fn () => $this->sayPhonetic($text, $escapeDigits), $buffer, $escapeDigits);
    }

    public function sayTimeCollect(string &$buffer, ?int $time = null, string $escapeDigits = ''): AgiResponse
    {
        return $this->fastPass(fn () => $this->sayTime($time, $escapeDigits), $buffer, $escapeDigits);
    }

    public function streamFileCollect(string &$buffer, string $filename, string $escapeDigits = '', int $offset = 0): AgiResponse
    {
        return $this->fastPass(
            fn () => $this->streamFile($filename, $escapeDigits, $offset),
            $buffer,
            $escapeDigits,
        );
    }

    public function getDataCollect(string &$buffer, string $filename, ?int $timeout = null, ?int $maxDigits = null): AgiResponse
    {
        if ($maxDigits !== null && strlen($buffer) >= $maxDigits) {
            return new AgiResponse(result: (int) $buffer);
        }

        if ($buffer === '') {
            $response = $this->getData($filename, $timeout, $maxDigits);
            if ($response->isOk()) {
                $buffer .= $response->result;
            }
            return $response;
        }

        while ($maxDigits === null || strlen($buffer) < $maxDigits) {
            $response = $this->waitForDigit();
            if (!$response->isOk()) {
                return $response;
            }
            if ($response->result === ord('#')) {
                break;
            }
            $buffer .= chr($response->result);
        }

        return new AgiResponse(result: (int) $buffer);
    }

    // -----------------------------------------------------------------------
    //  Menu
    // -----------------------------------------------------------------------

    public function menu(array $choices, int $timeout = 2000): string|int
    {
        $keys = implode('', array_keys($choices));

        while (true) {
            foreach ($choices as $prompt) {
                $response = $prompt[0] === '*'
                    ? $this->text2wav(substr($prompt, 1), $keys)
                    : $this->streamFile($prompt, $keys);

                if ($response instanceof AgiResponse && ($response->isFailure() || !$response->isOk())) {
                    return -1;
                }

                if ($response instanceof AgiResponse && $response->result !== 0) {
                    return $response->digit() ?? -1;
                }
            }

            $response = $this->getData('beep', $timeout, 1);
            if ($response->isFailure() || !$response->isOk()) {
                return -1;
            }

            $digit = $response->data !== '' ? $response->data : (string) $response->result;
            if ($digit !== '' && str_contains($keys, $digit)) {
                return $digit;
            }
        }
    }

    // -----------------------------------------------------------------------
    //  Internal: FastPass pattern
    // -----------------------------------------------------------------------

    private function fastPass(callable $sayFn, string &$buffer, string $escapeDigits): AgiResponse
    {
        if ($escapeDigits !== '' && $buffer !== '' && !str_contains($escapeDigits, $buffer[-1])) {
            /** @var AgiResponse $response */
            $response = $sayFn();
            if ($response->isOk() && $response->result > 0) {
                $buffer .= chr($response->result);
            }
            return $response;
        }

        if ($buffer === '') {
            /** @var AgiResponse $response */
            $response = $sayFn();
            if ($response->isOk() && $response->result > 0) {
                $buffer .= chr($response->result);
            }
            return $response;
        }

        return new AgiResponse(result: ord($buffer[-1]));
    }

    // -----------------------------------------------------------------------
    //  Internal: Evaluate AGI Command
    // -----------------------------------------------------------------------

    private function evaluate(string $command): AgiResponse
    {
        if (@fwrite($this->stdout, trim($command) . "\n") === false) {
            throw new ConnectionException("Failed to write command: {$command}");
        }
        fflush($this->stdout);

        $count = 0;
        do {
            $line = fgets($this->stdin, 4096);
            if ($line === false) {
                throw new ConnectionException("Failed to read response for: {$command}");
            }
            $line = trim($line);
        } while ($line === '' && $count++ < self::MAX_READ_ATTEMPTS);

        if ($count >= self::MAX_READ_ATTEMPTS) {
            throw new AgiException(
                message: 'No valid response received',
                command: $command,
            );
        }

        return $this->parseResponse($line, $command);
    }

    private function parseResponse(string $line, string $command): AgiResponse
    {
        $code = (int) substr($line, 0, 3);
        $rest = trim(substr($line, 3));

        if ($rest !== '' && $rest[0] === '-') {
            $count = 0;
            $rest = substr($rest, 1) . "\n";
            while (($next = fgets($this->stdin, 4096)) !== false && !str_starts_with($next, (string) $code) && $count < self::MAX_READ_ATTEMPTS) {
                $rest .= $next;
                $count = trim($next) === '' ? $count + 1 : 0;
            }
        }

        $result = null;
        $data = '';

        if ($code !== self::AGIRES_OK) {
            $data = $rest;
        } else {
            $parts = explode(' ', trim($rest));
            $dataParts = [];
            $inToken = false;

            foreach ($parts as $token) {
                if ($inToken) {
                    $dataParts[] = trim($token, '() ');
                    if (str_ends_with($token, ')')) {
                        $inToken = false;
                    }
                } elseif ($token === '') {
                    continue;
                } elseif ($token[0] === '(') {
                    $dataParts[] = trim($token, '() ');
                    if (!str_ends_with($token, ')')) {
                        $inToken = true;
                    }
                } elseif (str_contains($token, '=')) {
                    [$key, $val] = explode('=', $token, 2);
                    if ($key === 'result') {
                        $result = (int) $val;
                    }
                } else {
                    $dataParts[] = $token;
                }
            }

            $data = trim(implode(' ', $dataParts));
        }

        $response = new AgiResponse(code: $code, result: $result ?? -1, data: $data);

        if ($response->isFailure()) {
            $this->log("{$command} returned {$response->result}");
        }

        return $response;
    }

    // -----------------------------------------------------------------------
    //  Internal: Utilities
    // -----------------------------------------------------------------------

    public function log(string $message, int $level = 1): void
    {
        static $busy = false;

        if (!empty($this->config['phpagi']['debug']) && !$busy) {
            $busy = true;
            $this->verbose($message, $level);
            $busy = false;
        }
    }

    private function which(string $cmd, ?string $checkPath = null): string|false
    {
        $path = $checkPath ?? (getenv('PATH') ?: '/bin:/sbin:/usr/bin:/usr/sbin:/usr/local/bin:/usr/local/sbin');

        foreach (explode(':', $path) as $dir) {
            $candidate = "{$dir}/{$cmd}";
            if (is_executable($candidate)) {
                return $candidate;
            }
        }

        return false;
    }

    private function makeTempDir(string $folder, int $perms = 0755): bool
    {
        $parts = explode(DIRECTORY_SEPARATOR, $folder);
        $base = '';
        $count = count($parts);

        for ($i = 0; $i < $count; $i++) {
            $base .= $parts[$i];
            if ($parts[$i] !== '' && !file_exists($base)) {
                if (!mkdir($base, $perms)) {
                    return false;
                }
            }
            $base .= DIRECTORY_SEPARATOR;
        }

        return true;
    }

    private function openAudioStream(): void
    {
        $pid = getmypid();
        $paths = ["/proc/{$pid}/fd/3", '/dev/fd/3'];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                $this->audio = fopen($path, 'r');
                if ($this->audio !== false) {
                    stream_set_blocking($this->audio, 0);
                }
                return;
            }
        }

        $this->log('Unable to open audio stream');
    }

    private function loadConfig(): array
    {
        $paths = [
            defined('DEFAULT_PHPAGI_CONFIG')
                ? DEFAULT_PHPAGI_CONFIG
                : (defined('AST_CONFIG_DIR')
                    ? rtrim(AST_CONFIG_DIR, '/') . '/phpagi.conf'
                    : '/etc/asterisk/phpagi.conf'),
        ];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                $parsed = parse_ini_file($path, true);
                if ($parsed !== false) {
                    return $parsed;
                }
            }
        }

        return [];
    }

    private static function errorHandler(
        int $level,
        string $message,
        string $file,
        int $line,
        array $context,
    ): void {
        if (ini_get('error_reporting') === 0) {
            return;
        }

        @syslog(LOG_WARNING, "{$file}[{$line}]: {$message}");
    }
}
