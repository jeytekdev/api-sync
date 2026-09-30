<?php

declare(strict_types=1);

namespace Jeytekdev\ApiSync\Tests\Fixtures\Yii2;

/**
 * @api {post} /v1/sms/send Send an SMS
 * @apiName Send
 * @apiGroup Sms
 *
 * @apiUse HeaderRequest
 *
 * @apiParam {String} phone Phone number.
 */
class SmsController extends \api\components\controllers\Controller
{
    public function actionSend()
    {
    }
}
