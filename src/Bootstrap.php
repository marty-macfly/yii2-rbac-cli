<?php

namespace macfly\rbac;

use Yii;

class Bootstrap implements \yii\base\BootstrapInterface
{
  /** @inheritdoc */
  public function bootstrap($app)
  {
		if ($app instanceof ConsoleApplication)
		{
			$module->controllerNamespace = 'macfly\rbac\commands';
		}
  }
}
