<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
;

$config = new PhpCsFixer\Config();
return $config->setRules([
        '@PSR12' => true,
        'braces' => [
            'position_after_functions_and_oop_constructs' => 'same'
        ],
        'single_import_per_statement' => false,
        'no_blank_lines_after_class_opening' => false
    ])
    ->setFinder($finder)
;