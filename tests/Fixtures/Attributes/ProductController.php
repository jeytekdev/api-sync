<?php

declare(strict_types=1);

namespace Jeytekdev\ApiSync\Tests\Fixtures\Attributes;

use Jeytekdev\ApiSync\Attribute\Auth;
use Jeytekdev\ApiSync\Attribute\Group;
use Jeytekdev\ApiSync\Attribute\Param;
use Jeytekdev\ApiSync\Attribute\Response;
use Jeytekdev\ApiSync\Attribute\Summary;
use Jeytekdev\ApiSync\Attribute\Version;

class ProductController
{
    /**
     * Deliberately conflicting apidoc tag, to prove an explicit
     * #[Route] attribute is never overridden by a docblock @api tag.
     *
     * @api {post} /legacy/product/:id Old endpoint, ignored
     */
    #[Route('/products/{id}', methods: ['GET'], name: 'products.show')]
    #[Group('products')]
    #[Version('v1')]
    #[Auth('bearer')]
    #[Summary('Get a single product')]
    #[Param(name: 'id', in: 'path', type: 'int', required: true)]
    #[Response(status: 200, description: 'Product found')]
    #[Response(status: 404, description: 'Product not found')]
    public function show(int $id)
    {
    }
}
