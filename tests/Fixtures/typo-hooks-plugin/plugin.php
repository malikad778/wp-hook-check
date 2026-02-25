<?php

declare(strict_types=1);

add_action('my_plugin_user_registerd', 'send_welcome_email');

do_action('my_plugin_user_registered', $user_id ?? 0);
