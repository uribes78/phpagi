<?php

namespace Phpagi;

enum ChannelState: int
{
    case Down = 0;
    case Reserved = 1;
    case Offhook = 2;
    case Dialing = 3;
    case Ring = 4;
    case Ringing = 5;
    case Up = 6;
    case Busy = 7;
    case DialingOffhook = 8;
    case Prering = 9;

    public function label(): string
    {
        return match ($this) {
            self::Down => 'Channel is down and available',
            self::Reserved => 'Channel is down, but reserved',
            self::Offhook => 'Channel is off hook',
            self::Dialing => 'Digits (or equivalent) have been dialed',
            self::Ring => 'Line is ringing',
            self::Ringing => 'Remote end is ringing',
            self::Up => 'Line is up',
            self::Busy => 'Line is busy',
            self::DialingOffhook => 'Digits (or equivalent) have been dialed while offhook',
            self::Prering => 'Channel has detected an incoming call and is waiting for ring',
        };
    }

    public static function tryFromResult(int|string|null $result): ?self
    {
        if ($result === null || $result === -1) {
            return null;
        }
        return self::tryFrom((int) $result);
    }
}
