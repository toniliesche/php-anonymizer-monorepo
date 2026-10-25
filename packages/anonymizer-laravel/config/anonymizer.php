<?php

declare(strict_types=1);

return [
    'profiles' => [],
    'limits' => [
        'max_input_bytes' => 10_485_760,
        'max_depth' => 64,
        'max_visited_nodes' => 100_000,
        'max_collection_entries' => 50_000,
        'max_embedded_payload_depth' => 8,
    ],
    'faker' => ['enabled' => false, 'locale' => 'en_US', 'seed' => null],
    'personas' => ['enabled' => false, 'locale' => 'en'],
    'audit' => ['enabled' => false, 'logger_service' => null],
    'pseudonymization' => ['enabled' => false, 'key_provider' => null],
    'serializer' => ['enabled' => false, 'service' => null],
];
