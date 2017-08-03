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

# Import static role and permission list

Create a yaml file with le list of static role and permission you want to create

```yaml
# Permission section
permissions:
  list:
    desc: List user
  create:
    desc: Create user
  update:
    desc: Edit user
  profile:
    desc: Edit user profile
  delete:
    desc: Remove user

# Role section
roles:
  view:
    desc: View users
    children:
    - list
    - info
  admin:
    desc: Administration
    children:
    - view
    
# Assign permission and roles to a specific userid
assign:
  1:
  - admin
  - oauth.admin
  2:
  - user.info
```

After run the @rbac/yaml@ with the path to your yaml file

```
php yii rbac/load/yaml /tmp/role.yml
```

# Add role and permisison to a specific user

You can add some role and permission from the cli to a specific user id.

```
php yii rbac/load/add userid permissionOrRoleName
```

For example to add role 'view' to user with id '1' :


```
php yii rbac/load/add 1 view
```