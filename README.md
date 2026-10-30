# ez-php/encryption

Symmetric authenticated encryption for ez-php: an `Encrypter` over ext-sodium's
XChaCha20-Poly1305 AEAD with key rotation, a `Crypt` static façade, transparent cookie
encryption and an ORM cast for encrypted columns.

---

## Installation

```bash
composer require ez-php/encryption
```

Requires ext-sodium (bundled with standard PHP builds).

---

## Setup

```bash
ez encryption:key          # prints base64:...
```

```dotenv
ENCRYPTION_KEY=base64:...
ENCRYPTION_PREVIOUS_KEYS=          # comma-separated, decrypt-only
```

```php
// provider/modules.php
EzPhp\Encryption\EncryptionServiceProvider::class,
```

`config/encryption.php`:

```php
return [
    'key' => getenv('ENCRYPTION_KEY') ?: '',
    'previous_keys' => getenv('ENCRYPTION_PREVIOUS_KEYS') ?: '',
    'cookie_except' => ['XSRF-TOKEN'],
];
```

---

## Usage

```php
use EzPhp\Encryption\Crypt;
use EzPhp\Encryption\Encrypter;

$payload = Crypt::encrypt('secret');            // "ez1.…", URL- and cookie-safe
$plain = Crypt::decrypt($payload);              // throws DecryptException on tampering

// Bind a payload to a purpose — it only decrypts with the same context.
$token = $encrypter->encrypt($userId, 'password-reset');
$encrypter->decrypt($token, 'password-reset');
```

Inject `Encrypter` where you can; the façade exists for static call sites.

### Key rotation

1. `ez encryption:key` → new key.
2. Move the old key to `ENCRYPTION_PREVIOUS_KEYS`, set the new one as `ENCRYPTION_KEY`.
3. New payloads use the new key; old ones still decrypt. Drop the old key once nothing
   encrypted with it remains.

### Encrypted cookies

```php
$app->middleware(EzPhp\Encryption\Middleware\EncryptedCookieMiddleware::class);
```

Register it early in the global stack, before middleware that reads cookies through the
`Request` (e.g. `MaintenanceModeMiddleware`'s bypass cookie). It only sees cookies set on the
`Response` and read from the `Request`: PHP's native session cookie (`session_start()`,
`$_COOKIE`) never passes through it and stays an opaque, unencrypted id.
Handlers read and queue plaintext (`$request->cookie()`, `$response->withCookie()`); the
browser only sees ciphertext. A cookie that does not decrypt — tampered, copied from another
cookie name, or made with a retired key — is dropped. Cookies listed in
`encryption.cookie_except` (default `XSRF-TOKEN`) stay plaintext.

### Encrypted ORM columns (`ez-php/orm`)

```php
use EzPhp\Encryption\Orm\EncryptedString;

final class Account extends Entity
{
    protected static array $casts = ['iban' => EncryptedString::class];
}

$account->iban = EncryptedString::of('DE89 3704 0044 0532 0130 00');
$account->iban->value();   // plaintext
```

The column stores the payload (use `TEXT`/`VARCHAR(255)+`). An unchanged value keeps its
stored ciphertext, so loading and saving an entity does not mark it dirty.

---

## License

MIT
