<?php
namespace macfly\rbac\commands;

use Yii;

class RbacController extends \yii\console\Controller
{
  public function actionInit()
  {
    $auth = Yii::$app->authManager;

		$permissions	= [];

		foreach($this->permissions as $name => $infos)
		{
			$name = sprintf("%s.%s", Yii::$app->name, $name);
			if(is_null($permission = $auth->getPermission($name)))
			{
    		$permission = $auth->createPermission($name);
    		$permission->description = array_key_exists('desc', $infos) ? $infos['desc'] : '';
        $auth->add($permission);
			}
			$permissions[$name]	= $permission;
		}

		foreach($this->roles as $name => $infos)
		{
			$name = sprintf("%s.%s", Yii::$app->name, $name);
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
					$child = sprintf("%s.%s", Yii::$app->name, $child);
					if(!in_array($child, $children) && array_key_exists($child, $permissions))
					{
			      $auth->addChild($role, $permissions[$child]);
					}
				}
			}
		}

		if(is_null($role = $auth->getRole("admins")))
		{
    	$role = $auth->createRole("admins");
    	$role->description = "Administration of everythings";
      $auth->add($role);

			$child = sprintf("%s.admin", Yii::$app->name);
			if(!in_array($child, $children) && array_key_exists($child, $permissions))
			{
			  $auth->addChild($role, $permissions[$child]);
			}
		}
	}
}
