<?php

namespace macfly\rbac;

use Yii;

class Module extends \yii\base\Module
{
	/** @inheritdoc */
	public function init()
	{
		parent::init();
		if (Yii::$app instanceof \yii\console\Application) {
			$this->controllerNamespace = 'macfly\rbac\commands';
		}
	}
}
