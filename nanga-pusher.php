<?php

class Nanga_Deploy_Pusher
{

    public function __construct($params)
    {
        $this->params = $params;
    }

    public function commands()
    {
        $commands = [];
        $this->_commands_for_database_dump($commands);
        if ($this->params['ssh_db_host']) {
            $this->_commands_for_database_import_thru_ssh($commands);
        } else {
            $this->_commands_for_database_import_locally($commands);
        }
        $this->_commands_for_files($commands);
        $this->_commands_post_push($commands);

        return $commands;
    }

    protected function _commands_for_database_dump(&$commands)
    {
        extract($this->params);
        $siteurl        = get_option('siteurl');
        $searchreplaces = [$siteurl => $url, untrailingslashit(ABSPATH) => untrailingslashit($path)];
        $commands       = [
            [
                'wp db export db_original.sql',
                true,
            ],
        ];
        /*
        $this_path = untrailingslashit(ABSPATH);
        $that_path = untrailingslashit($path);
        $commands  = array(
            array(
                "wp migratedb export db_migrate.sql --exclude-post-revisions --find=$siteurl,$this_path --replace=$url,$that_path",
                true,
            ),
        );
        */
        foreach ($searchreplaces as $search => $replace) {
            $commands[] = ["wp search-replace $search $replace", true];
        }
        $commands[] = ['wp db export db_migrate.sql', true];
        $commands[] = ['wp db import db_original.sql', true];
        //$commands[] = array('rm db_original.sql', true);
    }

    protected function _commands_for_database_import_thru_ssh(&$commands)
    {
        extract($this->params);
        $commands[] = ["scp -P $ssh_port db_migrate.sql $ssh_db_user@$ssh_db_host:$ssh_db_path", true];
        $commands[] = [
            "ssh $ssh_db_user@$ssh_db_host -p $ssh_port \"cd $ssh_db_path; mysql --user=$db_user --password=$db_password --host=$db_host $db_name < db_migrate.sql; rm db_migrate.sql\"",
            true,
        ];
        //$commands[] = array('rm db_migrate.sql', true);
    }

    protected function _commands_for_database_import_locally(&$commands)
    {
        extract($this->params);
        $commands[] = [
            "mysql --user=$db_user --password=$db_password --host=$db_host $db_name < db_migrate.sql;",
            true,
        ];
        //$commands[] = array('rm db_migrate.sql', true);
    }

    protected function _commands_for_files(&$commands)
    {
        extract($this->params);
        //$remote_path = $path . '/';
        $remote_path = $path;
        $local_path  = ABSPATH;
        $excludes    = array_merge($excludes, [
            //'*.log',
            //'*.sql',
            //'.bowerrc',
            //'.DS_Store',
            //'.editorconfig',
            //'.ftpquota',
            '.git/',
            //'.gitignore',
            //'.gitmodules',
            '.idea/',
            //'.jshintrc',
            //'.local-config.php',
            //'.sass-cache/',
            //'_*.css',
            //'_*.js',
            //'_*.sh',
            //'assets/less/',
            //'assets/sass/',
            //'assets/scss/',
            'assets/vendor/',
            //'bower.json',
            'bower_components/',
            //'composer.json',
            //'composer.lock',
            //'error_log',
            //'Gruntfile.js',
            //'gulpfile.js',
            'node_modules/',
            //'package.json',
            //'sitemap.xml',
            //'sitemap.xml.gz',
        ]);
        // In case the destination env is in a subfolder of the source env,
        // we exclude the relative path to the destination to avoid infinite loop.
        /*
        if ( ! $ssh_host ) {
            $local_remote_path = realpath( $remote_path );
            if ( $local_remote_path ) {
                $local_path        = realpath( $local_path ) . '/';
                $local_remote_path = str_replace( $local_path . '/', '', $local_remote_path );
                $excludes[]        = $local_remote_path;
                $remote_path       = realpath( $remote_path ) . '/';
            }
        }
        */
        $excludes = array_reduce($excludes, function ($acc, $value) {
            $acc .= "--exclude \"$value\" ";

            return $acc;
        });
        //$exclude_from = '--exclude-from ' . plugin_dir_path(__FILE__) . 'excludes.conf';
        if ($ssh_host) {
            $command = "rsync -avz -e 'ssh -p $ssh_port' --no-o --no-g --chmod=Du=rwx,Dg=rx,Do=rx,Fu=rw,Fg=r,Fo=r $local_path $ssh_user@$ssh_host:$remote_path $excludes";
            //$command = "rsync -avz -e 'ssh -p $ssh_port' --no-o --no-g --chmod=Du=rwx,Dg=rx,Do=rx,Fu=rw,Fg=r,Fo=r $local_path $ssh_user@$ssh_host:$remote_path $exclude_from";
        } else {
            $command = "rsync -avz $local_path $remote_path $excludes";
        }
        $commands[] = [$command, true];
    }

    protected function _commands_post_push(&$commands)
    {
        extract($this->params);
        $const = strtoupper($env) . '_POST_SCRIPT';
        if (defined($const)) {
            $subcommand = constant($const);
            $commands[] = ["ssh $ssh_user@$ssh_host -p $ssh_port \"$subcommand\"", true];
        }
    }

    public function commands_for_files()
    {
        $commands = [];
        $this->_commands_for_files($commands);
        $this->_commands_post_push($commands);

        return $commands;
    }
}
