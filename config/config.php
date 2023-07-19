<?php

/*
 * You can place your custom package configuration in here.
 */
return [
    'blocks' => [
        'allow_blocks_inside_blocks' => true,
    ],
    'replacer' => [
        'prefix' => '{{',
        'suffix' => '}}',
        'prefix_error' => '{!{',
        'suffix_error' => '}!}',
        'end_block_prefix' => 'end',
        'delete_on_error' => false,
        'elements' => [
            'year' => date('Y'),
            'date' => date('d.m.Y'),
            'time' => date('H:i'),
            'datetime' => date('d.m.Y H:i'),
        ],
        'methods' => [
            'if' => true,
            'elseif' => true,
            'else' => true,
            'isset' => true,
            'empty' => true,
            'switch' => true,
            'for' => true,
            'foreach' => true,
            'translate' => true,
            'config' => true,
            'var' => true,
            'setvar' => true,
            'unsetvar' => true,
        ]
    ],
];
