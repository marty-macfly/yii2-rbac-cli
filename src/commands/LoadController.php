<?php
namespace macfly\rbac\commands;

use Yii;
use yii\console\Exception;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;

/**
 * This command manage permissions and roles.
 *
 * You can add permission and role to a user or import static permissions and roles from a file, for the moment only yaml file.
 */
class LoadController extends \yii\console\Controller
{
    public $defaultAction = 'yaml';
    public $filterOnAppName = true;

    protected $rules = [];
    protected $items = [];
    protected $auth = null;

    public function init()
    {
        if (!Yii::$app->has('authManager')) {
            $this->stderr("'authManager' is not enable, skipping static roles/permissions creation." . PHP_EOL, \yii\helpers\Console::BOLD);
            exit(\yii\console\Controller::EXIT_CODE_ERROR);
        }
    }

    public function options($actionID)
    {
        return ['filterOnAppName'];
    }

    /**
     * This command add role or permission to a specific user.
     * @userid integer the user id on which you want to add role or permission
     * @permissionOrRole string name of the role or permission you want to add
     */
    public function actionAdd($userid, $permissionOrRole)
    {
        if (($obj = Yii::$app->authManager->getPermission($permissionOrRole)) === null && ($obj = Yii::$app->authManager->getRole($permissionOrRole)) === null) {
            throw new Exception(sprintf("Permission or role '%s' doesn't exist", $permissionOrRole));
        }

        return Yii::$app->authManager->getAssignment($permissionOrRole, $userid) ? true : Yii::$app->authManager->assign($obj, $userid);
    }

    /**
     * This command load roles and permissions from a YAML file.
     * @file string path to yaml file to be loaded
     */
    public function actionYaml($file)
    {
        $this->fileExist($file);
        return $this->process(yaml_parse_file($file));
    }

    /**
     * This command load roles and permissions from a Json file.
     * @file string path to yaml file to be loaded
     */
    public function actionJson($file)
    {
        $this->fileExist($file);
        return $this->process(Json::decode($file, true));
    }

    protected function fileExist($file)
    {
        if (!file_exists($file)) {
            $this->stderr(sprintf("file '%s' doesn't exit" . PHP_EOL, $file), \yii\helpers\Console::BOLD);
            exit(\yii\console\Controller::EXIT_CODE_ERROR);
        }
    }

    protected function createOrUpdateItem($type, $name, $config)
    {
        $type = ucfirst($type);
        $isNew = false;

        if (($item = call_user_func([Yii::$app->authManager, 'get' . $type], $name)) === null) {
            $isNew = true;
            $item = call_user_func([Yii::$app->authManager, 'create' . $type], $name);
        }

        $item->description = ArrayHelper::getValue($config, 'desc', '');

        // Add rule
        if (($ruleName = ArrayHelper::getValue($config, 'rule')) !== null) {
            if (($rule = ArrayHelper::getValue($this->rules, $ruleName)) === null) {
                $rule = Yii::createObject($ruleName);
                if (Yii::$app->authManager->getRule($rule->name) === null) {
                    Yii::$app->authManager->add($rule);
                    $this->rules[$ruleName] = $rule;
                }
            }
            $item->ruleName = $rule->name;
        } else {
            $item->ruleName = null;
        }

        if ($isNew) {
            Yii::info(sprintf("Create item: %s", $name));
            Yii::$app->authManager->add($item);
        } else {
            Yii::info(sprintf("Update item: %s", $name));
            Yii::$app->authManager->update($name, $item);
        }

        $this->items[$name]	= $item;

        // Manage item children
        $children = Yii::$app->authManager->getChildren($name);

        // Delete children which have been removed.
        foreach (array_diff(array_keys($children), ArrayHelper::getValue($config, 'children', [])) as $child) {
            Yii::info(sprintf("Remove child %s from item: %s", $child, $name));
            if (($citem = Yii::$app->authManager->getPermission($child)) !== null || ($citem = Yii::$app->authManager->getRole($child)) !== null) {
                Yii::$app->authManager->removeChild($item, $citem);
            } else {
                Yii::warning(sprintf("Role/Permission %s doesn't exist", $child));
            }
        }

        // Add children
        foreach (ArrayHelper::getValue($config, 'children', []) as $child) {
            if (!in_array($child, $children)
                && ArrayHelper::keyExists($child, $this->items)
                && !Yii::$app->authManager->hasChild($item, $this->items[$child])) {
                Yii::info(sprintf("Add child %s to item: %s", $child, $name));
                Yii::$app->authManager->addChild($item, $this->items[$child]);
            }
        }
    }

    protected function removeItem($type, $name)
    {
        $type = ucfirst($type);

        if (($item = call_user_func([Yii::$app->authManager, 'get' . $type], $name)) !== null) {
            Yii::info(sprintf("Delete item: %s", $name));
            unset($this->items[$name]);
            return Yii::$app->authManager->remove($item);
        }

        return true;
    }

    protected function getItems($type)
    {
        $type = ucfirst($type);
        $items = call_user_func([Yii::$app->authManager, 'get' . $type . 's']);

        if ($this->filterOnAppName) {
            $items = array_filter($items, function ($value) {
                return strpos($value, \Yii::$app->name . '.') === 0;
            }, ARRAY_FILTER_USE_KEY);
        }

        return $items;
    }

    protected function process($data)
    {
        foreach (['permission', 'role'] as $type) {
            $items = ArrayHelper::getValue($data, $type . 's', []);

            if ($items === null) {
                continue;
            }

            // Delete unused role and permission
            foreach (array_diff(array_keys($this->getItems($type)), array_keys($items)) as $name) {
                $this->removeItem($type, $name);
            }

            // Add update role and permission
            foreach ($items as $name => $config) {
                $this->createOrUpdateItem($type, $name, $config);
            }
        }

        foreach (ArrayHelper::getValue($data, 'assign', []) as $userid => $permissionOrRoles) {
            foreach ($permissionOrRoles as $permissionOrRole) {
                try {
                    $this->actionAdd($userid, $permissionOrRole);
                } catch (Exception $exception) {
                    Yii::error(sprintf("%s", $exception->getMessage()));
                }
            }
        }
    }
}
