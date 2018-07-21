Debug Enabler for Nette framework
=================================

Aafe and clean way to automatize Nette Debug mode at specific environment (Docker for example).

Using
-----
In `bootstrap.php` wrap first parameter in `setDebugMode([])` function like this:
```php
$configurator->setDebugMode::isDebug(DebugEnabler([], __DIR__ . '/temp'));
```

In your devstack set for PHP process environment `NETTE_DEBUG=1`. For example in Docker composer file:
```yaml
environment:
    NETTE_DEBUG: 1
```

Danger zone
-----------
If know what do you do, you can call `DebugEnabler:turnOn()` to enable Debug mode for your browser. **This is very danger, use it carefully!**