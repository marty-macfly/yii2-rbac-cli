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

    /**
     * This command add role or permission to a specific user.
     * @userid integer the user id on which you want to add role or permission
     * @permissionOrRole string name of the role or permission you want to add
     */
    public function actionAdd($userid, $permissionOrRole)
    {
        $auth = Yii::$app->authManager;

        if(($obj = $auth->getPermission($permissionOrRole)) === null && ($obj = $auth->getRole($permissionOrRole)) === null)
        {
            throw new Exception(sprintf("Permission or role '%s' doesn't exist", $permissionOrRole));
        }

        return $auth->getAssignment($permissionOrRole, $userid) ? true : $auth->assign($obj, $userid);
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
        $auth = Yii::$app->authManager;
        $type = ucfirst($type);
        $isNew = false;

        if(($item = call_user_func([$auth, 'get' . $type], $name)) === null)
        {
            $isNew = true;
            $item = call_user_func([$auth, 'create' . $type], $name);
        }

        $item->description = ArrayHelper::getValue($infos, 'desc', '');
        $rule = null;
        if(($ruleName = ArrayHelper::getValue($infos, 'rule')) !== null) {
            if(($rule = ArrayHelper::getValue($this->rules, $ruleName)) === null) {
                $rule = Yii::createObject($ruleName);
                $auth->add($rule);
                $this->rules[$ruleName]	= $rule;
            }
        }
        $item->ruleName = $rule;
        $children = $auth->getChildren($name);

        print_r($children);
        print_r(ArrayHelper::getValue($infos, 'children',[]));

        foreach(ArrayHelper::getValue($infos, 'children',[]) as $child)
        {
            if(!in_array($child, $children)
                && ArrayHelper::keyExists($child, $this->items)
                && !$auth->hasChild($item, $this->items[$child]))
            {
                $auth->addChild($item, $permissions[$child]);
            }
        }

        if($isNew) {
            Yii::info(sprintf("Create item: %s", $name));
            $auth->add($item);
        } else {
            Yii::info(sprintf("Update item: %s", $name));
            $auth->update($name, $item);
        }

        $this->items[$name]	= $item;
    }

    protected function process($data)
    {
        foreach(ArrayHelper::getValue($data, 'permissions', []) as $name => $infos)
        {
            $this->createOrUpdateItem('permission', $name, $infos);
        }

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
