<?php
namespace macfly\rbac\commands;

use Yii;

/**
 * This command load permissions and roles from a file.
 *
 * This command load permissions and roles from a file, for the moment only yaml file.
 */
class LoadController extends \yii\console\Controller
{
	public $defaultAction = 'yaml';

  /**
   * This command load roles and permissions from a YAML file.
   * @param string $message the message to be echoed.
   */
	public function actionYaml($file)
	{
		if(!file_exists($file))
		{
			$this->stdout(sprintf("file '%s' doesn't exit", $file), \yii\helpers\Console::BOLD);
			return \yii\console\Controller::EXIT_CODE_ERROR;
		}

		return $this->process(yaml_parse_file($file));
	}

  protected function process($data)
  {
    $auth 				= Yii::$app->authManager;
		$permissions	= [];

		foreach($data['permissions'] as $name => $infos)
		{
			$name = sprintf("%s.%s", Yii::$app->name, $name);
			if(is_null($permission = $auth->getPermission($name)))
			{
echo ">$name\n";
print_r($infos);
    		$permission = $auth->createPermission($name);
    		$permission->description = array_key_exists('desc', $infos) ? $infos['desc'] : '';
        $auth->add($permission);
			}
			$permissions[$name]	= $permission;
		}

		if(array_key_exists('roles', $data))
		{
			foreach($data['roles'] as $name => $infos)
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
