# yii2-rbac-cli


Yii2 User and Rbac provider from another Yii2 instance for sso or cenralized way to manage user and role.

Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist "macfly/yii2-rbac-cli" "*"
```

or add

```
"macfly/yii2-rbac-cli": "*"
```

to the require section of your `composer.json` file.

Configure
------------

Configure **config/console.php** as follows

```php
  'modules' => [
     ................
    'rbac'  => [
      'class'       => 'macfly\rbac\Module',
    ],
    ................
  ],
```

Usage
------------

