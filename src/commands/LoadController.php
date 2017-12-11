<?php
namespace macfly\rbac\commands;

use Yii;
use yii\console\Exception;
use yii\helpers\ArrayHelper;

/**
 * This command manage permissions and roles.
 *
 * You can add permission and role to a user or import static permissions and roles from a file, for the moment only yaml file.
 */
class LoadController extends \yii\console\Controller
{
    public $defaultAction = 'yaml';

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
        if(!file_exists($file))
        {
            $this->stderr(sprintf("file '%s' doesn't exit", $file), \yii\helpers\Console::BOLD);
            return \yii\console\Controller::EXIT_CODE_ERROR;
        }

        return $this->process(yaml_parse_file($file));
    }

    protected function process($data)
    {
        $auth = Yii::$app->authManager;
        $permissions = [];
        $rules = [];

        if(ArrayHelper::keyExists('permissions', $data) && count($data['permissions']) > 0)
        {
            foreach($data['permissions'] as $name => $infos)
            {
                if(($permission = $auth->getPermission($name)) === null)
                {
                    $permission = $auth->createPermission($name);
                    $permission->description = ArrayHelper::getValue($infos, 'desc', '');
                    $auth->add($permission);
                }
                $permissions[$name]	= $permission;

                if(($ruleName = ArrayHelper::getValue($infos, 'rule')) !== null) {
                    if(($rule = ArrayHelper::getValue($rules, $ruleName)) === null) {
                        $rule = Yii::createObject($ruleName);
                        $auth->add($rule);
                        $rules[$ruleName]	= $rule;
                    }
                    $permission->ruleName = $rule;
                    $auth->update($permission);
                }
            }
        }

        if(ArrayHelper::keyExists('roles', $data) && count($data['roles']) > 0)
        {
            foreach($data['roles'] as $name => $infos)
            {
                if(($role = $auth->getRole($name)) === null)
                {
                    $role = $auth->createRole($name);
                    $role->description = ArrayHelper::getValue($infos, 'desc', '');
                    $auth->add($role);
                }

                $permissions[$name]	= $role;

                if(($ruleName = ArrayHelper::getValue($infos, 'rule')) !== null) {
                    if(($rule = ArrayHelper::getValue($rules, $ruleName)) === null) {
                        $rule = Yii::createObject($ruleName);
                        $auth->add($rule);
                        $rules[$ruleName]	= $rule;
                    }
                    $permission->ruleName = $rule;
                    $auth->update($permission);
                }

                $children = $auth->getChildren($name);

                if(ArrayHelper::keyExists('children', $infos))
                {
                    foreach($infos['children'] as $child)
                    {
                        if(!in_array($child, $children)
                            && ArrayHelper::keyExists($child, $permissions)
                            && !$auth->hasChild($role, $permissions[$child]))
                        {
                            $auth->addChild($role, $permissions[$child]);
                        }
                    }
                }
            }
        }

        if(ArrayHelper::keyExists('assign', $data) && count($data['assign']) > 0)
        {
            foreach($data['assign'] as $userid => $permissionOrRoles)
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
}
