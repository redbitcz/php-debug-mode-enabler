# PHP Debug Mode Enabler
> not only for Nette Tracy Debugger

Safe and clean way to manage Debug Mode in your app by specific environment and/or manually in app.
Package provide secure way to temporary switch Debug Mode of your App at any environment.

## Features
Package allows your app to switch to Debug Mode: 
- automatically on localhost's environment by IP,
- semi-automatically on any envirovnemnt where you set `PHP_APP_DEBUG_MODE` environment variable (useful for Docker dev-stack), 
- allows you to switch (force turn-on or turn-off) Debug Mode manually.

> NOTE: Package is NOT provide any Debug tools directly – it only gives the app whether to switch to debug mode.

## Installation
```bash
composer require redbitcz/debug-mode-enabler
```

## Requirements
Package is require PHP <=7.3 and temporary directory with writable access. 

## Using
Anywhere in your app you can determine if app is running in Debug mode by simple code:
```php
$detector = new \Redbitcz\DebugMode\DebugModeDetector($tempDir);
$debugMode = $detector->isDebugMode(); // boolean
```
where `$tempDir` is required absolute path to temporary directory.

It returns `$debugMode` = `true` when is detected Debug environment or manually switched.

### Using with Nette
In `\App\Bootstrap` class use package like this example:
```php
$tempDir = __DIR__ . '/../temp';
$debugModeDetector = new \Redbitcz\DebugMode\DebugModeDetector($tempDir);

$configurator = new Configurator();
$configurator->setDebugMode($debugModeDetector->isDebugMode());
```
> I know, you love DI Container to build services like this. But Container Loader need to know Debug Mode state before is
> DI Container ready, you cannot use DI for Debug Mode detecting. 

## Using with Docker
If you building custom Docker image for your devstack, add the environment variable `PHP_APP_DEBUG_MODE=1`. For example in `Dockerfile` file:
```
ENV PHP_APP_DEBUG_MODE 1
```
> Avoid to publish these image to production!

## Using with Docker compose
In your devstack set environment variable `PHP_APP_DEBUG_MODE=1`. For example in `docker-compose.yml` file:
```yaml
environment:
    PHP_APP_DEBUG_MODE: 1
```

## Manually switch
<p align="center">
  <img width="368" height="280" src="https://user-images.githubusercontent.com/1657322/78752208-f2354a00-7973-11ea-83ea-b2719e326dc8.png">
</p>

**WARNING – DANGER ZONE:** Following feature allows to force Debug Mode on any environment, production including.
Please use it with great caution only! Wrong use might cause to critical security issue! Before using Enabler's feature be
aware your app is resistant to XSS, CSRF and similar attacks!  

Enabler provide feature to force enable or disable Debug Mode anywhere for user's browser (drived by Cookie). 

This example turn on Debug Mode for user's browser:
```php
$enabler = new \Redbitcz\DebugMode\DebugModeEnabler($tempDir);
$enabler->activate(true);
```

### Options
- `$enabler->activate(true)` - force to Debug Mode turn on,
- `$enabler->activate(false)` - force to Debug Mode turn off,
- `$enabler->deactivate(false)` - reset back to automatically detection by environment.

### Using with Nette
Debug Mode Enabler (unlike Debug Mode Detector) can be simply served through DI Container with configuration in `config.neon`:
```yaml
services:
    - Redbitcz\DebugMode\DebugModeEnabler(%tempDir%)
```

At most cases this example is creates second instance of `DebugModeEnabler` class because first one is already created
internally with `DebugModeDetector` instance in `Bootstrap`.

To re-use already exists instance you can inject it to DI Container:
```php
$tempDir = __DIR__ . '/../temp';
$debugModeDetector = new \Redbitcz\DebugMode\DebugModeDetector($tempDir);

$configurator = new Configurator();
$configurator->setDebugMode($debugModeDetector->isDebugMode());
$configurator->addServices(['debugModeEnabler' => $debugModeDetector->getEnabler()]);
```

Don't forget let it know to DI Container with service declaration in `config.neon`: 
```yaml
services:
    debugModeEnabler:
        type: Redbitcz\DebugMode\DebugModeEnabler
```  

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
