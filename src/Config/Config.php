<?php

declare(strict_types=1);

namespace Adnan\WpHookAuditor\Config;

readonly class Config
{

    public function __construct(
        public array $exclude = ['vendor/', 'node_modules/', 'tests/'],
        public array $detectors = [
            'orphaned_listener' => true,
            'unheard_hook'      => true,
            'typo'              => true,
            'dynamic_hook'      => false,
        ],
        public array $ignore = [],
        public array $externalPrefixes = [
            'wp_',
            'admin_',
            'woocommerce_',
            'acf/',
            'elementor/',
            'pre_',
            'the_',
            'get_',
            'save_',
            'delete_',
            'after_',
            'before_',
            'user_',
            'comment_',
            'post_',
            'term_',
            'nav_',
            'login_',
            'register_',
            'heartbeat_',
            'customize_',
            'oembed_',
            'rest_',
            'xmlrpc_',
            'auth_',
            'send_',
            'retrieve_',
            'transition_',
            'deprecated_',
            'plugins_',
            'map_',
            'query_',
            'cron_',
            'option_',
            'network_',
            'site_',
            'menu_',
            'phpmailer_',
            'shutdown',
            'init',
            'parse_request',
            'plugins_loaded',
            'plugin_action_links',
            'map_meta_cap',
            'screen_',
            'set_screen_',
        ],
    ) {}

    public function isDetectorEnabled(string $name): bool
    {
        return $this->detectors[$name] ?? false;
    }

    public function isIgnored(string $type, string $hookName): bool
    {
        foreach ($this->ignore as $rule) {
            if (($rule['type'] ?? '') === $type && ($rule['hook'] ?? '') === $hookName) {
                return true;
            }
        }

        return false;
    }

    public function isExternalHook(string $hookName): bool
    {
        foreach ($this->externalPrefixes as $prefix) {
            if (str_starts_with($hookName, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
