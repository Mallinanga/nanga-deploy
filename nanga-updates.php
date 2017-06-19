<?php

class Nanga_Deploy_Updates
{

    private $author;
    private $name;
    private $remote_info_url;
    private $remote_version_url;
    private $slug;
    private $version;

    public function __construct()
    {
        $this->author             = 'Panos Paganis';
        $this->name               = 'VG web things Deployer';
        $this->remote_info_url    = 'https://api.github.com/repos/Mallinanga/nanga-deploy';
        $this->remote_version_url = 'https://api.github.com/repos/Mallinanga/nanga-deploy/releases';
        $this->slug               = 'nanga-deploy';
        $this->version            = '1.1.2';
        add_filter('plugins_api', array($this, 'inject_info'), 10, 3);
        add_filter('pre_set_site_transient_update_plugins', array($this, 'inject_update'));
        add_filter('upgrader_post_install', array($this, 'post_install'), 10, 3);
    }

    public function inject_update($transient)
    {
        $remote_version = $this->remote_version();
        if (version_compare($remote_version->tag_name, $this->version, '<=')) {
            return $transient;
        }
        $obj                                                           = new stdClass();
        $obj->slug                                                     = $this->slug;
        $obj->plugin                                                   = $this->slug . '/' . $this->slug . '.php';
        $obj->new_version                                              = $remote_version->tag_name;
        $obj->url                                                      = $this->remote_info_url;
        $obj->package                                                  = $remote_version->zipball_url;
        $transient->response[$this->slug . '/' . $this->slug . '.php'] = $obj;

        return $transient;
    }

    private function remote_version()
    {
        if ( ! empty($_GET['force-check'])) {
            if (empty($_GET[$this->slug . '-ignore-force-check'])) {
                delete_transient($this->slug . '_cached_version');
            }
            $_GET[$this->slug . '-ignore-force-check'] = true;
        }
        $cached_version = get_transient($this->slug . '_cached_version');
        if ($cached_version) {
            return $cached_version;
        }
        $request = wp_remote_get($this->remote_version_url);
        if ( ! is_wp_error($request) || wp_remote_retrieve_response_code($request) === 200) {
            $version = wp_remote_retrieve_body($request);
            $version = @json_decode($version);
            $version = $version[0];
            set_transient($this->slug . '_cached_version', $version, 30 * MINUTE_IN_SECONDS);

            return $version;
        }
        set_transient($this->slug . '_cached_version', 0, 5 * MINUTE_IN_SECONDS);

        return 0;
    }

    public function inject_info($result, $action, $args)
    {
        if (isset($args->slug) && $args->slug == $this->slug) {
            $remote_version = $this->remote_version();
            $info           = array(
                'name'         => $this->name,
                'slug'         => $this->slug,
                'version'      => $remote_version->tag_name,
                'author'       => $this->author,
                'last_updated' => $remote_version->published_at,
                'tested'       => get_bloginfo('version'),
                'sections'     => array(
                    'changelog' => $remote_version->body,
                ),
            );
            $obj            = new stdClass();
            foreach ($info as $k => $v) {
                $obj->$k = $v;
            }

            return $obj;
        }

        return $result;
    }

    public function post_install($true, $hook_extra, $result)
    {
        global $wp_filesystem;
        $proper_destination = WP_PLUGIN_DIR . '/' . $this->slug;
        $wp_filesystem->move($result['destination'], $proper_destination);
        $result['destination'] = $proper_destination;

        return $result;
    }

    private function remote_info()
    {
        $request = wp_remote_get($this->remote_info_url);
        if ( ! is_wp_error($request) || wp_remote_retrieve_response_code($request) === 200) {
            $info = wp_remote_retrieve_body($request);
            $info = @json_decode($info);

            return $info;
        }

        return false;
    }
}
