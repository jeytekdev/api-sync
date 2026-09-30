<?php

declare(strict_types=1);

namespace Jeytekdev\ApiSync\Tests\Fixtures\Yii2;

/**
 * A plain (non-ActiveController) Yii2 controller with a project-local
 * base class and a non-CRUD action name - Yii2PatternExtractor can only
 * guess "GET /phone/validate" for this, while the real route is
 * documented (and versioned/POST) in the apidoc.js block below.
 */
class PhoneController extends \api\components\controllers\Controller
{
    /**
     * @api {post} /v1/phone/validate Validate a phone number
     * @apiName Validate
     * @apiGroup Phone
     * @apiParam {String} phone Phone number
     */
    public function actionValidate()
    {
    }
}
