<?php

return array(
    'routes' => array(
        // url for GET manual trigger      DEFAULT: /trigger_update
        'manual' => '/trigger_update',
        // url for POST automatic trigger  DEFAULT: /trigger_update
        'auto' => '/trigger_update',

        // filter for manual trigger route (e.g. if you want to 'auth.admin' first)  DEFAULT: <none>
        'manual_filter' => null,
    ),

    // branch to listen for pushes for and to pull from  DEFAULT: master
    'branch' => 'master',
    // site name - used in various display functions     DEFAULT: $_SERVER['SERVER_NAME']
    'site_name' => null,
    // length limit for commit hash (0 for full hash)    DEFAULT: 0
    'commit_hash_length' => 7,

    'email' => array(
        // from info for notification email  DEFAULT: as specified in app/config/mail.php; or <site_name> Self Updater <update@$_SERVER['SERVER_NAME']>
        'from' => array('address' => 'update@example.com', 'name' => 'Sample Self-Updater'),
        // target for notification email  REQUIRED
        'to' => 'update-notify@example.com',
        // reply-to for notification email  DEFAULT: <from>
        'reply_to' => 'dev-team@example.com',

        //subject can be a static string, or a callback (or either split out into success/failure subject lines)
        //'subject'  => 'Self-Updater was triggered',
        //'subject'  => function($site_name, $success, $commit_hash) { return $site_name . ' Self-Updater ' . ($success ? 'Success: ' . $commit_hash : 'ERROR'); },
        'subject' => array(
            'success' => 'Self-Updater success', // can also be callback function like above
            'failure' => 'Self-Updater failure',
        ),
    ),
);
