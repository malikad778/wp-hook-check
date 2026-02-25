<?php

declare(strict_types=1);

add_action('my_checkout_complete', 'send_confirmation_email');

add_action('my_payment_processed', 'update_inventory');

add_action('my_plugin_valid_hook', 'handle_valid_hook');
do_action('my_plugin_valid_hook');
