PHP Debug Mode Enabler
======================

> not only for Nette Tracy

Safe and clean way to automatize Nette Debug mode at specific environment (Docker for example).

Installation
------------
```bash
composer require redbitcz/debug-mode-enabler
```

Using
-----
In `bootstrap.php` wrap first parameter in `setDebugMode([])` function like this:
```php
$configurator->setDebugMode(DebugEnabler::isDebug([], __DIR__ . '/temp'));
```

In your devstack set for PHP process environment `NETTE_DEBUG=1`. For example in Docker composer file:
```yaml
environment:
    NETTE_DEBUG: 1
```

Danger zone
-----------
If know what do you do, you can call `DebugEnabler:turnOn()` to enable Debug mode for your browser. **This is very danger, use it carefully!**

Advanced
--------
You can choose `Env` or `Token` detection method only instead of automatic detection.

Detection by Environment only:
```php
DebugEnabler::isDebugByEnv([])
```

Detection by Token (and cookie) only:
```php
DebugEnabler::isDebugByToken([])
```

What's the magic `[]`?\
It's used as return value when no Debug mode is detected and it's move decision to automatic rule
in Nette Debugger. Use `false` if you want to prevent transfer of detect mode to Debugger.

Is set `workDir` required?\
Yes if you want to use detect by Token (cookie). 


License
-------
The MIT License (MIT)

Copyright (c) 2020 Redbit s.r.o., Jakub Bouček

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
