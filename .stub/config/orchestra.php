<?php

return [
   'master' =>
   [
      'domain' => '',
      'name'   => '',
      'route' => [
         [
            'name' => 'master',
            'prefix' => 'api',
            'middleware' => 'api',
            'file_name' => 'api.php',
         ],
         [
            'name' => 'master',
            'prefix' => 'web',
            'middleware' => 'web',
            'file_name' => 'web.php',
         ]
      ],
   ],

   'slave' => [
      'route' => [
         [
            'name' => 'slave',
            'prefix' => 'api',
            'middleware' => 'api',
            'file_name' => 'api.php',
         ],
         [
            'name' => 'slave',
            'prefix' => 'web',
            'middleware' => 'web',
            'file_name' => 'web.php',
         ]
      ],
   ],

   'providers' => [],
];
