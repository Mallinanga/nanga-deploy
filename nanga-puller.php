<?php

class Nanga_Deploy_Puller
{

    public function __construct($params)
    {
        $this->params = $params;
    }

    public function commands()
    {
        $commands = array();
        if ($this->params['ssh_db_host']) {
            $this->_commands_for_database_import_thru_ssh($commands);
        } else {
            $this->_commands_for_database_import_locally($commands);
        }
        $this->_commands_for_database_dump($commands);
        //$commands[] = array( 'rm db.sql', true );
        $this->_commands_for_files($commands);

        return $commands;
    }

    public function commands_for_files()
    {
        $commands = array();
        $this->_commands_for_files($commands);

        return $commands;
    }

    protected function _commands_for_files(&$commands)
    {
        extract($this->params);
        $dir = wp_upload_dir();
        //$dist_path   = constant( Nanga_Deploy_Command::config_constant( 'path' ) ) . '/';
        $dist_path   = constant(Nanga_Deploy_Command::config_constant('path'));
        $remote_path = $dist_path;
        $local_path  = ABSPATH;
        $excludes    = array_merge($excludes, array(
            '_*.*',
            '*.log',
            '*.sql',
            '.git',
            '.idea',
            //'.sass-cache',
            //'assets/vendor',
            //'bower_componets',
            'local-config.php',
            //'node_modules',
            //'wp-content/cache',
            //'wp-config.php',
        ));
        // In case the source env is in a subfolder of the destination env, we exclude the relative path to the source to avoid infinite loop.
        /*
        if ( ! $ssh_host ) {
            $remote_local_path = realpath( $local_path );
            if ( $remote_local_path ) {
                $remote_path       = realpath( $remote_path ) . '/';
                $remote_local_path = str_replace( $remote_path . '/', '', $remote_local_path );
                $excludes[]        = $remote_local_path;
            }
        }
        */
        $excludes = array_reduce($excludes, function ($acc, $value) {
            $acc .= "--exclude \"$value\" ";

            return $acc;
        });
        if ($ssh_host) {
            $commands[] = array(
                "rsync -avz -e 'ssh -p $ssh_port' $ssh_user@$ssh_host:$remote_path $local_path $excludes",
                true,
            );
        } else {
            $commands[] = array("rsync -avz $remote_path $local_path $excludes", true);
        }
    }

    protected function _commands_for_database_import_thru_ssh(&$commands)
    {
        extract($this->params);
        $host       = $db_host . ':' . $db_port;
        $dist_path  = constant(Nanga_Deploy_Command::config_constant('path')) . '/';
        $commands[] = array("ssh $ssh_user@$ssh_host -p $ssh_port \"cd $dist_path;wp db export dump.sql;\"", true);
        $commands[] = array("scp $ssh_user@$ssh_host:$dist_path/dump.sql .", true);
        $commands[] = array("ssh $ssh_user@$ssh_host -p $ssh_port \"cd $dist_path; rm db.sql;\"", true);
    }

    protected function _commands_for_database_import_locally(&$commands)
    {
        extract($this->params);
        //write_log( $this->params );
        //$host = $db_host . ':' . $db_port;
        $host = $db_host;
        //$wpdb = new wpdb( $db_user, $db_password, $db_name, $host );
        $path = ABSPATH;
        //$url      = get_bloginfo( 'url' );
        $url       = get_option('siteurl');
        $dist_path = constant(Nanga_Deploy_Command::config_constant('path')) . '/';
        //$commands[] = array( "wp migrate to $path $url dump.sql", true );
    }

    protected function _commands_for_database_dump(&$commands)
    {
        extract($this->params);
        $commands[]     = array('wp db export db_bk.sql', true);
        $commands[]     = array('wp db import dump.sql', true);
        $siteurl        = get_option('siteurl');
        $searchreplaces = array($url => $siteurl, untrailingslashit($path) => untrailingslashit(ABSPATH));
        foreach ($searchreplaces as $search => $replace) {
            $commands[] = array("wp search-replace $search $replace", true);
        }
    }
}
