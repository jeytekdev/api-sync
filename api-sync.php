<?php

declare(strict_types=1);

return [
    'name' => 'api-sync',
    'baseUrl' => 'https://api.example.test',
    'source' => [
        __DIR__ . '/src',
    ],
    'out' => __DIR__ . '/api-collections',
    'formats' => ['postman', 'insomnia', 'bruno'],
    'prune' => false,
    'environmentName' => null,
];
