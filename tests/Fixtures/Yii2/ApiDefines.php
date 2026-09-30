<?php

declare(strict_types=1);

// A file whose only purpose is holding shared apidoc.js @apiDefine blocks,
// exactly like a shared base controller in a real Yii2 project would -
// @apiUse in a completely different file must still resolve it.

/**
 * @apiDefine HeaderRequest
 * @apiHeader {String} Authorization login:password base64-encoded as "Basic [login:password]".
 */
