PHP Debug Mode Enabler
======================

Safe and clean way to manage Debug Mode in your app by specific environment and/or manually in App.
Package automatically detects development environments and provide secure way to temporary switch Debug Mode of your App
at any environment.

## Features
Package allows your app to switch to Debug Mode:
- automatically on localhost's environment by IP,
- semi-automatically on any environment where you set `APP_DEBUG` environment variable (useful for Docker dev-stack),
- semi-automatically **disable** Debug mode on any environment where you set `app-debug-mode` cookie variable (useful for
tests and similar cases),
- manually enable/disable (force turn-on or turn-off) Debug Mode.

**NOTE:** Package does NOT provide any Debug tools directly – it only tells the app whether to switch to debug mode.

Package is optimized for invoking in very early lifecycle phase of your App

## Requirements
Package requires:

- PHP 8.3 or newer within PHP 8.x (`^8.3`)
- `ext-json`
- Nette Utils `^3.0 || ^4.0 || dev-master`

The Utils development branch is allowed for PHP 8.6 compatibility until a compatible
stable release is available.

Enabler requires:
 
- Temporary directory with writable access

SignUrl plugin requires:

- [Firebase JWT](https://github.com/firebase/php-jwt) v7

## Installation
```shell
composer require redbitcz/debug-mode-enabler
```

## Using
Anywhere in your app you can determine if app is running in Debug mode by simple code:
```php
use Redbitcz\DebugMode\Detector;

$debugMode = Detector::detect(); //bool
```

It returns `$debugMode` = `true` when it detects Debug environment or manually switched.

`detect()` and `isDebugMode()` always return `bool`. If no rule decides, they return `false`.
`detectProduction()` and `isProductionMode()` return the opposite result, defaulting to `true`.

For advanced detection without a fallback, use an instance:

```php
$detector = new Detector();
$result = $detector->resolve(); // true, false, or null when no rule decides
$debugMode = $result ?? false; // application-defined fallback
```

See [UPGRADE.md](UPGRADE.md) for migration from v5 to v6.


### Using with Nette
In Boostrap use package like in this example:
```php
$debugModeDetector = new Detector();

$configurator = new Configurator();
$configurator->setDebugMode($debugModeDetector->isDebugMode());
```
> I know, you love DI Container to build services like this. But Container Loader need to know Debug Mode state before is
> DI Container ready, you cannot use DI for Debug Mode detecting.

## Using with Docker
If you are building custom Docker image for your devstack, add the environment variable `APP_DEBUG=1`. For example in `Dockerfile` file:
```
ENV APP_DEBUG 1
```
> Avoid to publish these image to production!

## Using with Docker compose
In your devstack set environment variable `APP_DEBUG=1`. For example in `docker-compose.yml` file:
```yaml
environment:
    APP_DEBUG: 1
```

## Manually switch
**WARNING – DANGER ZONE:** Following feature allows you to force Debug Mode on any environment, including *production*.
Please use it with great caution only! Wrong use might cause critical security issue! Before using Enabler's feature, make sure your app is resistant to XSS, CSRF and similar attacks!  

Enabler provide feature to force enable or disable Debug Mode anywhere for user's browser (drived by Cookie).

This example turn on Debug Mode for user's browser:

```php
use Redbitcz\DebugMode\Detector;
use Redbitcz\DebugMode\Enabler;

$enabler = new Enabler($tempDir);
$detector = new Detector(Detector::ModeFull, $enabler);

$enabler->activate(true);
```

### Options
- `$enabler->activate(true)` - force to Debug Mode turn on,
- `$enabler->activate(false)` - force to Debug Mode turn off,
- `$enabler->deactivate()` - reset back to automatically detection by environment.

### Using with Nette
Debug Mode Enabler (unlike Debug Mode Detector) can be simply served through DI Container with configuration in `config.neon`:
```yaml
services:
    - Redbitcz\DebugMode\Enabler(%tempDir%)
```

At most cases this example creates second instance of `Enabler` class because first one is already created
internally with `Detector` instance in `Bootstrap`.

To re-use already existing instance you can inject it to DI Container:

```php
$tempDir = __DIR__ . '/../temp';
$enabler = new Enabler($tempDir);
$debugModeDetector = new Detector(Detector::ModeFull, $enabler);

$configurator = new Configurator();
$configurator->setDebugMode($debugModeDetector->isDebugMode());
$configurator->addServices(['debugModeEnabler' => $debugModeDetector->getEnabler()]);
```

Don't forget letting know DI Container with service declaration in `config.neon`:
```yaml
services:
    debugModeEnabler:
        type: Redbitcz\DebugMode\Enabler
        imported: true
```  

## Plugins

Detector supports custom plugin. You can build custom plugin to provide your own roles to manage Debug Mode. Plugin must
implements `Plugin` interface what means add `__invoke()` method. That method is called always is Detector aksed to
detect mode.

Plugin retuns result of detection:

- `null` – no result – Detector will try to ask another plugin or detection method to decide
- `true` – force turn-on debug mode for current request
- `false` – force turn-off debug mode for current request

Note: You should return `null` value when Plugin doesn't explicitly matches rule. Boolean value is always stops
processing detection rules.

Don't do this:

```php
if (…) {
    return true;
} else {
    return false;
}
```

instead return `null` when your rule is not matched: 

```php
if (…) {
    return true;
} else {
    return null;
}
```

Your Plugin you can register to Detector with method `appendPlugin()` or `prepedndPlugin()`.

```php
$detector = new Detector();

$plugin = new MyPlugin();

$detector->appendPlugin($plugin);

$detector->isDebugMode(); // <---- this invoke all Plugins
```

## SignUrl plugin

Install the optional JWT dependency when using this plugin:

```shell
composer require firebase/php-jwt:^7.0
```

The Detector itself does not require JWT.


`SignUrl` plugin provide secure way to share link with activated Debug Mode. 

```php
use Redbitcz\DebugMode\Plugin\SignedUrl;

// Load a persistent secret from your application's configuration.
// Generate it once with base64_encode(random_bytes(32)) for HS256.
$plugin = SignedUrl::create($secretKey, 'HS256', 'https://myapp.cz');
$detector->appendPlugin($plugin);

$signedUrl = $plugin->signUrl('https://myapp.cz/failingPage', '+1 hour');

echo 'Private link with activated Debug mode: ' . htmlspecialchars($signedUrl, ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE);
```

### Security notes

Wrong usage of the `SignUrl` plugin can open critical vulnerability issue at your App. Follow this instructions:  

- Always create `SignUrl` with strong and Secret key, use key generator like: `base64_encode(random_bytes(32))`
- Always create `SignUrl` with specified `$audience` parameter which distinguishes versions of app (stage vs production)
to prevent unwanted re-using signatures between them
([read more about importance of audience](https://stackoverflow.com/a/41237822/1641372)).

## License
The MIT License (MIT). Please see [License File](LICENSE) for more information.
