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

    protected $rules = [];
    protected $items = [];
    protected $auth = null;

    public function init()
    {
        if(!Yii::$app->has('authManager')) {
            $this->stderr("'authManager' is not enable", \yii\helpers\Console::BOLD);
            exit(\yii\console\Controller::EXIT_CODE_ERROR);
        }

        $this->auth = Yii::$app->authManager;
    }

    /**
     * This command add role or permission to a specific user.
     * @userid integer the user id on which you want to add role or permission
     * @permissionOrRole string name of the role or permission you want to add
     */
    public function actionAdd($userid, $permissionOrRole)
    {
        if(($obj = $this->auth->getPermission($permissionOrRole)) === null && ($obj = $this->auth->getRole($permissionOrRole)) === null)
        {
            throw new Exception(sprintf("Permission or role '%s' doesn't exist", $permissionOrRole));
        }

        return $this->auth->getAssignment($permissionOrRole, $userid) ? true : $this->auth->assign($obj, $userid);
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
        if(!file_exists($file))
        {
            $this->stderr(sprintf("file '%s' doesn't exit", $file), \yii\helpers\Console::BOLD);
            exit(\yii\console\Controller::EXIT_CODE_ERROR);
        }
    }

    protected function createOrUpdateItem($type, $name, $infos) {
        $type = ucfirst($type);
        $isNew = false;

        if(($item = call_user_func([$this->auth, 'get' . $type], $name)) === null)
        {
            $isNew = true;
            $item = call_user_func([$this->auth, 'create' . $type], $name);
        }

        $item->description = ArrayHelper::getValue($infos, 'desc', '');

        // Add rule
        $rule = null;
        if(($ruleName = ArrayHelper::getValue($infos, 'rule')) !== null) {
            if(($rule = ArrayHelper::getValue($this->rules, $ruleName)) === null) {
                $rule = Yii::createObject($ruleName);
                if ($this->auth->getRule($rule->name) === null) {
                    $this->auth->add($rule);
                }
            }
            $this->rules[$ruleName] = $rule;
        }

        $item->ruleName = $rule;

        // Manage item children
        $children = $this->auth->getChildren($name);

        // Delete children which have been removed.
        foreach(array_diff(array_keys($children), ArrayHelper::getValue($infos, 'children',[])) as $child) {
            Yii::info(sprintf("Remove child %s from item: %s", $child, $name));
            $this->auth->removeChild($item, $permissions[$child]);
        }

        // Add children
        foreach(ArrayHelper::getValue($infos, 'children',[]) as $child)
        {
            if(!in_array($child, $children)
                && ArrayHelper::keyExists($child, $this->items)
                && !$this->auth->hasChild($item, $this->items[$child]))
            {
                Yii::info(sprintf("Add child %s to item: %s", $child, $name));
                $this->auth->addChild($item, $this->items[$child]);
            }
        }

        if($isNew) {
            Yii::info(sprintf("Create item: %s", $name));
            $this->auth->add($item);
        } else {
            Yii::info(sprintf("Update item: %s", $name));
            $this->auth->update($name, $item);
        }

        $this->items[$name]	= $item;
    }

    protected function process($data)
    {
        foreach(ArrayHelper::getValue($data, 'permissions', []) as $name => $infos)
        {
            $this->createOrUpdateItem('permission', $name, $infos);
        }

        print_r($this->auth->getPermissions());
        print_r(ArrayHelper::getValue($data, 'permissions', []));
        print_r(array_diff(array_keys($this->auth->getPermissions()), array_keys(ArrayHelper::getValue($data, 'permissions', []))));

        foreach(ArrayHelper::getValue($data, 'roles', []) as $name => $infos)
        {
            $this->createOrUpdateItem('role', $name, $infos);
        }

        foreach(ArrayHelper::getValue($data, 'assign', []) as $userid => $permissionOrRoles)
        {
            foreach($permissionOrRoles as $permissionOrRole)
            {
                try {
                    $this->actionAdd($userid, $permissionOrRole);
                } catch (Exception $exception) {
                    Yii::error(sprintf("%s", $exception->getMessage()));
                }
            }
        }
    }
}
