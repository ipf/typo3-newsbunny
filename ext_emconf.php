<?php

$EM_CONF['news_bunny'] = [
    'title' => 'NewsBunny',
    'description' => 'Backend administration module for EXT:news: news record list with filters, storage page listing and shortcuts for creating news, categories and tags',
    'category' => 'backend',
    'author' => 'ipf',
    'author_email' => '',
    'author_company' => 'ipf',
    'state' => 'stable',
    'clearCacheOnLoad' => true,
    'version' => '1.3.2',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-14.99.99',
            'news' => '13.0.0-14.99.99',
        ],
        'conflicts' => [
            'news_administration' => '*',
        ],
    ],
];
