SimpleAssetManager
==================

Extremely simple asset manager for PHP. If you need anything advanced I'd recommend you to take a look at [Assetic](https://github.com/kriswallsmith/assetic).

How does it work?
-----------------

You need to configure it

```php
\crodas\Asset\Configure::get()
 ->store('/tmp/map.php') // It is where temporary info is stored, to speed up things
 ->detDir('js', __DIR__ . '/public/js', '/js')                                                   
 ->setDir('css', __DIR__ . '/public/css', '/css');
```

Then you can simple call it from your views

```php
echo Asset::css('base.css', 'style.css');
echo Asset::js('jquery.js', 'jquery-ui.js');
```

Todo
----
 1. Add unit tests
 2. Less/scss support
 3. Ability to define packages ahead of time (for instance define `jquery.js` = `jquery.js` + `jquery-ui.js`)
