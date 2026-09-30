<?php

declare(strict_types=1);

namespace app\api\v1\controllers;

class PhoneController extends BaseController
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
