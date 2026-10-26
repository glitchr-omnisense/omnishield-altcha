# omnishield/altcha

ALTCHA as an Omnishield captcha: a proof of work the site issues and checks itself, over
`altcha-org/altcha` (the PHP library, ^2.3) and the `altcha` widget 3.3.0 (npm, MIT). The
visitor's browser derives keys until one starts as the challenge asks; the site checks the one it
found. It calls nothing: no third party, no cookie, no consent to ask - the family's default. Since
ALTCHA cannot tell a solution it saw before, the gateway remembers each one in the store of spent
tokens it is given.

## Installation

```sh
composer require omnishield/altcha
```

PHP 8.2 or later, `omnisense/omnishield` and `altcha-org/altcha` ^2.3. No HTTP client: this package
calls nothing.

```php
use Omnishield\Altcha\AltchaGatewayFactory;
use Omnishield\Replay\CacheReplayStore;

$gateway = (new AltchaGatewayFactory(new CacheReplayStore($psr6Pool)))->create(['hmac_key' => getenv('ALTCHA_HMAC_KEY')]);
```

## Credentials

None from anyone: an `hmac_key` of the site's own - long, random, secret, from the environment or
the secrets vault - signs the challenges. Changing it makes the challenges already in pages
invalid (`invalidSignature`).

## The sources

Written on 2026-10-07 from the library's own code and README at
[v2.3.0](https://github.com/altcha-org/altcha-lib-php/tree/v2.3.0) (`Altcha::createChallenge()`,
`solveChallenge()`, `verifySolution()`, `Payload`, `CreateChallengeOptions`), and from the widget's
README and code, `altcha` 3.3.0 on npm (its attributes, the inline JSON challenge it accepts, the
base64 payload it posts in the `altcha` field).

## How it goes

1. `widget($action)` - or `issue($action)`, behind a route - makes a **challenge**: random nonce
   and salt, a key derivation (PBKDF2/SHA-256 by default) and its cost, the prefix the derived key
   must start with, an expiry, and `{"action": ..., "issued": ...}` in its data - all of it signed
   with HMAC-SHA-256 under the site's `hmac_key`.
2. The widget's workers try counters until a derived key starts with the prefix, and post
   `{"challenge": ..., "solution": {"counter", "derivedKey"}}`, base64, in the `altcha` field.
3. `verify()` checks the signature, the expiry, the solution (one derivation), the action - and
   spends the challenge's signature in the store: the second post is `duplicate`.

| Verdict | When |
|---|---|
| passed | signed, unexpired, solved, for the expected action, first time |
| `missing` | nothing posted |
| `invalid` (`malformed`) | not base64 JSON of a payload - the widget's test mode included |
| `invalid` (`invalidSignature`) | not signed with this site's key |
| `invalid` (`invalidSolution`) | the derived key does not hold |
| `expired` | past its `expires` |
| `action` | issued for another action, or for none when one is expected |
| `duplicate` | posted before |

`Verdict::$action` is the action it was issued for, `$at` when it was issued; no score, no host:
ALTCHA does not know where the widget was shown.

## Options

| Option | Type | Default | Required | |
|---|---|---|---|---|
| `hmac_key` | string | - | yes | signs the challenges: long, random, secret - an environment variable |
| `algorithm` | string | `PBKDF2/SHA-256` | no | `PBKDF2/SHA-384`, `PBKDF2/SHA-512`, `SHA-256`, `SHA-384`, `SHA-512`: what the widget bundles; another: `InvalidConfigException` |
| `cost` | int | `5000` | no | the derivation's iterations: the work of one try (at least 1) |
| `key_prefix` | string, hexadecimal | `00` | no | what the derived key must start with: each hexadecimal pair multiplies the tries by 256; not hexadecimal: `InvalidConfigException` |
| `expires` | int, seconds | `600` | no | how long a challenge may be solved and posted in (at least 1) |
| `challenge_url` | string | `null` | no | where the widget fetches its challenge (`?action=` added); none: the challenge is in the page |
| `field` | string | `altcha` | no | the posted field |
| `script` | string | jsDelivr's `altcha@3.3.0/dist/main/altcha.min.js` (`AltchaGatewayFactory::SCRIPT`); under the Symfony and Laravel bridges, the site's own copy (`/omnishield/altcha/3.3.0/altcha.min.js`, `SCRIPT_PATH`) | no | the widget's script; `null` prints none |
| `integrity` | string | `null`: `AltchaGatewayFactory::INTEGRITY` for the two addresses above | no | the script's Subresource Integrity, for another address |
| `attributes` | map | `{}` | no | `<altcha-widget>`'s own attributes: `auto` (`onsubmit`, `onfocus`, `onload`), `display`, `language`, `type`, `theme`, `workers` |
| `configuration` | map | `{}` | no | its `configuration` attribute, as JSON: `{hideFooter: true}`, `{minDuration: 1000}` |
| `language` | string | `null`: the visitor's (under the Symfony bridge, the request's) | no | the widget's language, its `language` attribute: `fr`, `de`, `pt-br` (`fr_FR` is written `fr-fr`) |
| `strings` | map | `{}` | no | its texts, by name (`AltchaGateway::TEXTS`): `{label: "Pas un robot", verified: "Merci"}` |

`new AltchaGatewayFactory(?ReplayStoreInterface $replays = null)`: the store of spent tokens,
shared by every request (`CacheReplayStore` on a PSR-6 pool, or one of the site's on an atomic
store); none is this process's memory (`InMemoryReplayStore`), right in a test or a worker, not
behind PHP-FPM. `AltchaGatewayFactory::algorithm($name)` gives the library's key derivation of a
name (a solver of your own, the tests).

## Capabilities

| | |
|---|---|
| is this token valid (`ChallengeInterface`) | yes |
| it issues its challenges (`ChallengeIssuerInterface::issue($action)`) | yes: the JSON the widget fetches |
| texts in the visitor's language (`LocalizableInterface`) | yes: `texts()`, `language()`, `localized($language, $strings)` |
| the action checked | yes: signed into the challenge's data |
| the host checked | no: ALTCHA does not know where the widget was shown |
| a score | no |
| a token used twice | refused (`duplicate`), by the store of spent tokens |
| third party, cookies | none, none (the script: the site's, or jsDelivr's in PHP alone) |

The widget is printed as `<script type="module" async defer>` and `<altcha-widget challenge="..."
name="altcha">`.

## The script: the site's own

The widget's script - `altcha` 3.3.0's `dist/main/altcha.min.js`, MIT - is **shipped in this
package** (`public/altcha.min.js`, its licence beside it, `AltchaGatewayFactory::SCRIPT_FILE`),
unchanged: its integrity is `sha256-/CeoPc2Yo9is9tcPvkZD3L7lEqv7mIhu3Y7QRTUK2Ck=`
(`AltchaGatewayFactory::INTEGRITY`), the same as the CDN's. It loads nothing else: its workers are
data: URLs (`worker-src 'self' data:` in a Content-Security-Policy).

**In a Symfony application** the bridge serves it at `/omnishield/altcha/3.3.0/altcha.min.js`
(`AltchaGatewayFactory::SCRIPT_PATH`, cached a year) and makes that the gateways' default: the
widget reaches nobody - `Widget::$origins` is empty, `reachesOthers()` false, no consent to ask.
Nothing to install, no route to import, no asset pipeline. `omnishield.serve_scripts: false` goes
back to the CDN.

**In PHP alone** the default stays jsDelivr, pinned and checked (`AltchaGatewayFactory::SCRIPT`):
`Widget::$origins` then names `https://cdn.jsdelivr.net` - a CDN sees the visitor's address, though
the check itself stays on the site (`thirdParty: false`). Serve the package's copy yourself and
nobody does:

```sh
cp vendor/omnishield/altcha/public/altcha.min.js public/js/altcha.min.js
```

```php
$gateway = (new AltchaGatewayFactory($store))->create(['hmac_key' => getenv('ALTCHA_HMAC_KEY'), 'script' => '/js/altcha.min.js']);
```

## Its texts, in the visitor's language

The script carries English only. Its texts - "I'm not a robot", "Verifying...", "Verified",
"Verification failed. Try again later.", the footer "Protected by ALTCHA" and the others of
`AltchaGateway::TEXTS` - are given to it by a short script printed after the element
(`Widget::$inline`, its nonce with the others), which registers them with the widget
(`globalThis.$altcha.i18n`) for its `language`; a text left out stays the widget's English.

**In a Symfony application** the bridge does it for every widget it prints: the request's locale,
the texts of its translation domain `omnishield` (`altcha.label`, `altcha.verifying`...), shipped in
French, English, German and Japanese - an application's `translations/omnishield.<locale>.yaml`
overrides any of them, or adds a language. The `language` option fixes the widget's language
whatever the visitor's; the `strings` option wins over the translations:

```yaml
omnishield:
    gateways:
        forms:
            factory: altcha
            options:
                hmac_key: '%env(ALTCHA_HMAC_KEY)%'
                strings: { label: "Je ne suis pas un robot, promis" }
```

**In PHP alone** give them as options, or to `localized()`:

```php
$gateway = $factory->create(['hmac_key' => $key, 'language' => 'fr', 'strings' => ['label' => 'Je ne suis pas un robot', 'verifying' => 'Vérification…', 'verified' => 'Vérifié']]);
$french = $gateway->localized('fr', $texts);   // a copy; the gateway's own language and strings still win
```

## In the page, or from a route

Without `challenge_url` the challenge travels in the page, as the widget's `challenge` attribute
(JSON). A page served from a cache would give everyone the same challenge - the first to post it
spends it: let the widget fetch a fresh one, from the Symfony bridge's route
(`/omnishield/<gateway>/challenge`) or one of yours that answers `$gateway->issue($action)` with
`Cache-Control: no-store`.

## Example

A widget in French, its challenge solved by the library's own solver (what the browser does), then
posted twice and for another action (run in omnishield's workspace on 2026-10-26, offline - the
date printed is that run's):

```php
<?php

require 'vendor/autoload.php';

use AltchaOrg\Altcha\Altcha;
use AltchaOrg\Altcha\Challenge;
use AltchaOrg\Altcha\Payload;
use AltchaOrg\Altcha\SolveChallengeOptions;
use Omnishield\Altcha\AltchaGatewayFactory;
use Omnishield\Model\Attempt;
use Omnishield\Replay\InMemoryReplayStore;

$altcha = (new AltchaGatewayFactory(new InMemoryReplayStore()))->create(['hmac_key' => 'a-long-random-secret-of-the-site', 'language' => 'fr', 'strings' => ['label' => 'Je ne suis pas un robot']]);
$widget = $altcha->widget('contact');
echo $widget->tag, ' posting "', $widget->field, '", origins: ', implode(', ', $widget->origins), ', texts: ', $widget->inline ? 'yes' : 'no', "\n";

// The browser's part, done here by altcha-org/altcha's own solver.
$solve = static function (array $issued): string {
    $challenge = Challenge::fromArray($issued);
    $solution = (new Altcha())->solveChallenge(new SolveChallengeOptions(challenge: $challenge, algorithm: AltchaGatewayFactory::algorithm('PBKDF2/SHA-256')));

    return (new Payload($challenge, $solution))->toBase64();
};
$token = $solve(json_decode($widget->attributes['challenge'], true));
foreach (['contact', 'contact'] as $action) {
    $verdict = $altcha->verify(new Attempt($token, action: $action));
    echo $verdict->passed ? 'passed, issued at '.$verdict->at->format(DATE_ATOM) : 'refused ('.implode(', ', $verdict->reasons).')', "\n";
}
echo 'another action: ', implode(', ', $altcha->verify(new Attempt($solve($altcha->issue('signup')), action: 'contact'))->reasons), "\n";
echo 'garbage: ', implode(', ', $altcha->verify(new Attempt('not-a-payload'))->codes), "\n";
```

```
altcha-widget posting "altcha", origins: https://cdn.jsdelivr.net, texts: yes
passed, issued at 2026-10-25T23:46:40+00:00
refused (duplicate)
another action: action
garbage: malformed
```

In Symfony, with the challenge fetched from the bridge's route (the page may then be cached):

```yaml
omnishield:
    gateways:
        forms:
            factory: altcha
            options: { hmac_key: '%env(ALTCHA_HMAC_KEY)%', challenge_url: /omnishield/forms/challenge }
```

## Errors

ALTCHA calls nothing, so it is never unreachable and never refuses a key: a token that does not
hold is a `Verdict` (the table above). `InvalidConfigException` at `create()`: `hmac_key` missing
or empty, an unknown `algorithm`, a `key_prefix` that is not hexadecimal.

## Verified, and not

| | |
|---|---|
| The family's conformance suite (`Omnishield\Testing\GatewayContractTests`) | **green on 2026-10-25**, offline: `Tests/AltchaContractTest.php` - challenges issued and solved here, no request made |
| The whole way in PHP | **done on 2026-10-07**, by the tests and `docker compose run --rm omnishield bare --live`: a challenge issued, solved by `altcha-org/altcha`'s own `solveChallenge()` (what the widget does), verified, refused the second time (`duplicate`) and for another action (`action`); a challenge signed with another key, a wrong solution, an expired challenge refused |
| The widget's script on jsDelivr | fetched on 2026-10-07: served, an ES module, its SHA-256 the `integrity` above |
| The copy shipped in `public/` | **done on 2026-10-07**: npm's tarball `altcha-3.3.0.tgz` (the registry's SHA-1 checked), its `dist/main/altcha.min.js` of the same SHA-256 as jsDelivr's - asserted by the tests; licence MIT, its text beside it; read for what it loads: nothing (data: workers) |
| The bridge serving it | **done on 2026-10-07** by the tests: the address answers the file, `text/javascript`, cached a year; the widget built by the bridge points there, no origin. In a browser: not done |
| The widget in a browser | **not done**: no browser was run. Its attributes, its inline challenge and the payload it posts are read from its README and its code (`altcha` 3.3.0) |
| Its texts | **done on 2026-10-07** by the tests: the `language` attribute and the script registering the texts, the bridge's four catalogues complete (every name of `TEXTS`), the request's locale followed, `language` and `strings` winning. The registry's API (`$altcha.i18n.get/set`) and the way the widget picks its language (its attribute, `<html lang>`, the browser's) read from its code (`altcha` 3.3.0). In a browser: not done |
| Other algorithms | `SHA-256` by the tests; the PBKDF2 variants by the library's own |
| `CacheReplayStore` under concurrent requests | not exercised: PSR-6 has no atomic add - see the store's note |

## Responsibility

ALTCHA here reaches no service: the challenge is issued and checked by the site. The widget's
script is the work of Daniel Regeci and BAU Software s.r.o., under the MIT License, shipped
unchanged with its licence (`public/altcha.LICENSE.txt`); the PHP library is `altcha-org/altcha`'s.
This package is not affiliated with their authors; its MIT licence covers its own code.
