<?php
namespace macfly\rbac\commands;

use Yii;
use yii\console\Exception;

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

        if(Yii::$app->identity->findIdentity($userid) === null)
        {
            throw new Exception(sprintf("User id '%d' doesn't exist", $userid));
        }

        return $auth->getAssignment($obj, $userid) ? true : $auth->assign($obj, $userid);
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
        $auth 				= Yii::$app->authManager;
        $permissions	= [];

        if(array_key_exists('permissions', $data) && count($data['permissions']) > 0)
        {
            foreach($data['permissions'] as $name => $infos)
            {
                if(is_null($permission = $auth->getPermission($name)))
                {
                    $permission = $auth->createPermission($name);
                    $permission->description = array_key_exists('desc', $infos) ? $infos['desc'] : '';
                    $auth->add($permission);
                }
                $permissions[$name]	= $permission;
            }
        }

        if(array_key_exists('roles', $data) && count($data['roles']) > 0)
        {
            foreach($data['roles'] as $name => $infos)
            {
                if(is_null($role = $auth->getRole($name)))
                {
                    $role = $auth->createRole($name);
                    $role->description = array_key_exists('desc', $infos) ? $infos['desc'] : '';
                    $auth->add($role);
                }

                $permissions[$name]	= $role;
                $children						= $auth->getChildren($name);

                if(array_key_exists('children', $infos))
                {
                    foreach($infos['children'] as $child)
                    {
                        if(!in_array($child, $children)
                            && array_key_exists($child, $permissions)
                            && !$auth->hasChild($role, $permissions[$child]))
                        {
                            $auth->addChild($role, $permissions[$child]);
                        }
                    }
                }
            }
        }

        if(array_key_exists('assign', $data) && count($data['assign']) > 0)
        {
            foreach($data['assign'] as $userid => $permissionOrRole)
            {
                try {
                    $this->actionAdd($userid, $permissionOrRole);
                } catch(\Exception $exception)
                {
                    Yii::error(sprintf("%s", $exception->msg));
                }
            }
        }
    }
}
