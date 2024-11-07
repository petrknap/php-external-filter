# Library for easier work with external filters

Encapsulation of a command along with its options which provides a filter method.
Its primary role is to **facilitate filtering operations within a pipeline**,
allowing for easy chaining and execution of executable filters.

```php
namespace PetrKnap\ExternalFilter;

# echo "H4sIAAAAAAAAA0tJLEkEAGPz860EAAAA" | base64 --decode | gzip --decompress
echo (
    new Filter('base64', ['--decode'])
)->pipe(
    new Filter('gzip', ['--decompress'])
)->filter('H4sIAAAAAAAAA0tJLEkEAGPz860EAAAA');
```

If you want to process external data, redirect output or get errors, you can use input, output or error streams.

```php
namespace PetrKnap\ExternalFilter;

$errorStream = fopen('php://memory', 'w+');

(new Filter('php'))->filter(
    '<?php fwrite(fopen("php://stderr", "w"), "error");',
    error: $errorStream,
);

rewind($errorStream);
echo stream_get_contents($errorStream);
fclose($errorStream);
```

---

Run `composer require petrknap/external-filter` to install it.
You can [support this project via donation](https://petrknap.github.io/donate.html).
The project is licensed under [the terms of the `LGPL-3.0-or-later`](./COPYING.LESSER).
