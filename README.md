# PHPAGI — Modern PHP 8.x Asterisk Gateway Interface

[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.1-777BB4)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-LGPL--2.1--or--later-blue)](COPYING)

A modern, fully-typed PHP library for building Asterisk AGI applications. This is a complete overhaul of the legacy [phpagi](https://github.com/welltime/phpagi) library, rewritten for PHP 8.1+ with modern OOP practices, strict typing, and PSR-4 autoloading.

---

## Installation

```bash
composer require tico/phpagi
```

## Quick Start

```php
use Phpagi\AgiClient;

$agi = new AgiClient();

$agi->answer();

$name = $agi->getVariable('CALLERID(name)');
$agi->sayNumber(1234);

$agi->hangup();
```

## Architecture

```
src/Phpagi/
├── AgiClient.php              # Main AGI client implementation
├── AgiInterface.php           # Contract interface
├── AgiRequest.php             # Immutable request DTO
├── AgiResponse.php            # Immutable response DTO
├── CallerId.php               # CallerID value object
├── ChannelState.php           # Backed enum for channel states
└── Exception/
    ├── AgiException.php       # Base exception
    └── ConnectionException.php # Stream errors
```

## Full Example

```php
use Phpagi\AgiClient;
use Phpagi\CallerId;
use Phpagi\ChannelState;

$agi = new AgiClient();
$agi->answer();

$status = $agi->channelStatus();
$state = ChannelState::tryFromResult($status->result);

if ($state === ChannelState::Up) {
    $agi->streamFile('welcome');
    $input = $agi->getData('beep', 3000, 1);

    if ($input->digit() === '1') {
        $agi->execDial('SIP', '100', 30000);
    }
}

$cid = CallerId::parse($agi->getRequest()->callerId);
$agi->setVariable('CALLER_NAME', $cid->name);
$agi->hangup();
```

## Changes from the Original (2.20)

| Original (`phpagi.php`) | Modern (`Phpagi\AgiClient`) |
|---|---|
| Global functions, no namespace | PSR-4 namespaced under `Phpagi\` |
| `var` properties, no type hints | Typed `readonly` DTOs, strict types everywhere |
| Returns raw arrays: `['code'=>500, 'result'=>-1]` | Throws `AgiException` on errors |
| Array access: `$agi->request['agi_callerid']` | Typed object: `$agi->getRequest()->callerId` |
| Global constants: `AST_STATE_UP` | Backed enum: `ChannelState::Up` |
| `parse_callerid()` returns array | `CallerId::parse()` returns typed value object |
| `join()`, `substr()`, `strpos()` | `match`, `str_contains`, `str_starts_with`, `str_ends_with` |
| Error codes in return values | Structured exception hierarchy |
| PHP 4 compatible syntax | PHP 8.1+ features: enums, union types, named arguments, property promotion |

## PHP 8.x Features Used

- **Enums** — `ChannelState` backed enum replaces global integer constants
- **Readonly properties** — `AgiRequest`, `AgiResponse`, `CallerId` are immutable DTOs
- **Union types** — `string|int|float`, `string|false`, `AGI|false`
- **Named arguments** — Cleaner constructor/ method calls
- **Match expressions** — Replaces large `switch` blocks
- **String functions** — `str_contains()`, `str_starts_with()`, `str_ends_with()`
- **Null coalescing assignment** — `$config['key'] ??= 'default'`
- **Constructor property promotion**
- **`never` / `mixed` / `void` return types**

## Documentation

Legacy documentation for the original API is preserved in `src/deprecated/` and `docs/`.

## License

LGPL-2.1-or-later. See [COPYING](COPYING).
