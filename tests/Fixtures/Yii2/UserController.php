<?php

declare(strict_types=1);

namespace Jeytekdev\ApiSync\Tests\Fixtures\Yii2;

class UserController extends \yii\rest\ActiveController
{
    /**
     * @api {get} /user List users
     * @apiName ListUsers
     * @apiGroup users
     * @apiParam {Number} [page] Page number.
     * @apiSuccess {Object[]} items List of users.
     */
    public function actionIndex()
    {
    }

    /**
     * @api {get} /user/:id Get a single user
     * @apiParam {Number} id User ID.
     * @apiSuccess {String} firstname Firstname of the user.
     * @apiError (404) NotFound User does not exist.
     */
    public function actionView(int $id)
    {
    }

    public function actionCreate()
    {
    }

    public function actionDelete(int $id)
    {
    }
}
