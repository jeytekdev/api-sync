<?php

declare(strict_types=1);

namespace app\api\v1\controllers;

use yii\filters\auth\HttpBasicAuth;
use yii\helpers\ArrayHelper;
use yii\rest\Controller;

class BaseController extends Controller
{
    public function behaviors()
    {
        return ArrayHelper::merge(parent::behaviors(), [
            'authenticator' => [
                'class' => HttpBasicAuth::className(),
            ],
        ]);
    }
}
