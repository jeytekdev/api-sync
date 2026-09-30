<?php

use app\api\v1\Module;
use yii\rest\UrlRule;

return [
    'modules' => [
        'v1' => [
            'class' => Module::class,
        ],
    ],
    'components' => [
        'urlManager' => [
            'rules' => [
                [
                    'class' => UrlRule::class,
                    'controller' => ['v1/phone'],
                    'extraPatterns' => [
                        'POST validate' => 'validate',
                    ],
                ],
                [
                    'class' => UrlRule::class,
                    'controller' => ['v1/hlr'],
                    'pluralize' => false,
                    'except' => ['index', 'view', 'update', 'delete', 'create'],
                    'extraPatterns' => [
                        'POST' => 'send',
                    ],
                ],
                [
                    'class' => UrlRule::class,
                    'controller' => ['v1/firebase'],
                    'pluralize' => false,
                    'except' => ['index', 'view', 'update', 'delete', 'create'],
                    'extraPatterns' => [
                        'GET score-for-recaptcha/{os}' => 'score-for-recaptcha',
                    ],
                ],
                'GET api/status' => 'status/index',
            ],
        ],
    ],
];
