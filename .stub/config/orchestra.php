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
         ],
         [
            'name' => 'master',
            'prefix' => 'console',
            'middleware' => 'console',
            'file_name' => 'console.php',
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
         ],
         [
            'name' => 'slave',
            'prefix' => 'console',
            'middleware' => 'console',
            'file_name' => 'console.php',
         ]
      ],
   ],

   'providers' => [],
   'virtual_hosts_config_prefix' => 'orchestra-', //exemple: orchestra-domain.com.conf
   'cron_log_path' => 'orchestra-deployer.log', //cron log will be save in storage/logs/orchestra-deployer.log
];
