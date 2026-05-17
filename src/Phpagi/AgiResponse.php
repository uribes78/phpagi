<?php

namespace Phpagi;

final readonly class AgiResponse
{
    public int $code;
    public int $result;
    public string $data;

    public function __construct(
        int $code = 200,
        int $result = 0,
        string $data = '',
    ) {
        $this->code = $code;
        $this->result = $result;
        $this->data = $data;
    }

    public function isOk(): bool
    {
        return $this->code === 200;
    }

    public function isFailure(): bool
    {
        return $this->result < 0;
    }

    public function digit(): ?string
    {
        return $this->result > 0 ? chr($this->result) : null;
    }

    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'result' => $this->result,
            'data' => $this->data,
        ];
    }

    public static function fromRawResponse(array $parsed): self
    {
        return new self(
            code: (int) ($parsed['code'] ?? 500),
            result: (int) ($parsed['result'] ?? -1),
            data: (string) ($parsed['data'] ?? ''),
        );
    }
}
