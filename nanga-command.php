<?php
WP_CLI::add_command( 'deploy', 'Nanga_Deploy_Command' );

/**
 * Deploys WordPress to different environments.
 *
 * @package nanga-deploy
 * @author Panos Paganis
 */
class Nanga_Deploy_Command extends WP_CLI_Command {
    protected static $_env;

    /**
     * Push local to remote, both system and database
     *
     * @synopsis <environment> [--dry-run]
     *
     * @param array $args
     * @param array $flags
     */
    public function push( $args = array(), $flags = array() ) {
        $this->_push_command( 'commands', $args, $flags );
    }

    /**
     * Push local to remote, only filesystem
     *
     * @synopsis <environment> [--dry-run]
     *
     * @param array $args
     * @param array $flags
     */
    public function push_files( $args = array(), $flags = array() ) {
        $this->_push_command( 'commands_for_files', $args, $flags );
    }

    /**
     * Push local to remote, only media [INCOMPLETE]
     *
     * @synopsis <environment> [--dry-run]
     *
     * @param array $args
     * @param array $flags
     */
    public function push_media( $args = array(), $flags = array() ) {
        //$this->_push_command( 'commands_for_files', $args, $flags );
    }

    /**
     * Pull local from remote, both system and database
     *
     * @synopsis <environment> [--dry-run]
     *
     * @param array $args
     * @param array $flags
     */
    public function pull( $args = array(), $flags = array() ) {
        $this->_pull_command( 'commands', $args, $flags );
    }

    /**
     * Pull local from remote, only filesystem
     *
     * @synopsis <environment> [--dry-run]
     *
     * @param array $args
     * @param array $flags
     */
    public function pull_files( $args = array(), $flags = array() ) {
        $this->_pull_command( 'commands_for_files', $args, $flags );
    }

    /**
     * Pull local from remote, only media [INCOMPLETE]
     *
     * @synopsis <environment> [--dry-run]
     *
     * @param array $args
     * @param array $flags
     */
    public function pull_media( $args = array(), $flags = array() ) {
        //$this->_pull_command( 'commands_for_files', $args, $flags );
    }

    /**
     * Put local environment to sleep.
     *
     * @synopsis <environment>
     *
     * @param array $args
     * @param array $flags
     */
    public function sleep( $args = array(), $flags = array() ) {
        $this->params = self::_prepare_and_extract( $args );
        extract( $this->params );
        write_log( $this->params );
        if ( defined( 'WP_ENV' ) && WP_ENV ) {
            if ( 'development' == WP_ENV && 'local' == $env ) {
                WP_CLI::line( WP_ENV . ' ' . $env );
                //WP_CLI::launch( $command )
                //WP_CLI::run_command( $args, $assoc_args = array() )
            } else {
                return WP_CLI::error( WP_ENV . ' cannot be put to sleep.' );
            }
        }
    }

    protected function _push_command( $command_name, $args, $flags ) {
        $this->params = self::_prepare_and_extract( $args );
        $this->flags  = $flags;
        extract( $this->params );
        if ( $locked === true ) {
            return WP_CLI::error( "$env environment is locked, you cannot push to it." );
        }
        require 'nanga-pusher.php';
        $reflectionMethod = new ReflectionMethod( 'Nanga_Deploy_Pusher', $command_name );
        $commands         = $reflectionMethod->invoke( new Nanga_Deploy_Pusher( $this->params ) );
        $this->_execute_commands( $commands, $args );
    }

    protected function _pull_command( $command_name, $args, $flags ) {
        $this->params = self::_prepare_and_extract( $args );
        $this->flags  = $flags;
        extract( $this->params );
        if ( $locked === true ) {
            return WP_CLI::error( "$env environment is locked, you can not pull to your local copy." );
        }
        require 'nanga-puller.php';
        $reflectionMethod = new ReflectionMethod( 'Nanga_Deploy_Puller', $command_name );
        $commands         = $reflectionMethod->invoke( new Nanga_Deploy_Puller( $this->params ) );
        $this->_execute_commands( $commands, $args );
    }

    protected function _execute_commands( $commands ) {
        if ( isset( $this->flags['dry-run'] ) ) {
            write_log( $this->params );
        }
        foreach ( $commands as $command_info ) {
            list( $command, $exit_on_error ) = $command_info;
            if ( isset( $this->flags['dry-run'] ) ) {
                WP_CLI::line( $command );
            }
            if ( ! isset( $this->flags['dry-run'] ) ) {
                WP_CLI::launch( $command, $exit_on_error );
            }
        }
    }

    protected static function _prepare_and_extract( $args ) {
        $out        = array();
        self::$_env = $args[0];
        $errors     = self::_validate_config();
        if ( $errors !== true ) {
            foreach ( $errors as $error ) {
                WP_CLI::error( $error );
            }

            return false;
        }
        $out                = self::config_constants_to_array();
        $out['env']         = self::$_env;
        $out['db_user']     = escapeshellarg( $out['db_user'] );
        $out['db_host']     = escapeshellarg( $out['db_host'] );
        $out['db_password'] = escapeshellarg( $out['db_password'] );
        $out['ssh_port']    = ( isset( $out['ssh_port'] ) ) ? intval( $out['ssh_port'] ) : 22;
        $out['excludes']    = explode( ':', $out['excludes'] );

        return $out;
    }

    protected static function _validate_config() {
        $errors = array();
        foreach ( array( 'path', 'url', 'db_host', 'db_user', 'db_name', 'db_password' ) as $postfix ) {
            $required_constant = self::config_constant( $postfix );
            if ( ! defined( $required_constant ) ) {
                $errors[] = "$required_constant is not defined";
            }
        }
        if ( count( $errors ) == 0 ) {
            return true;
        }

        return $errors;
    }

    public static function config_constant( $postfix ) {
        return strtoupper( self::$_env . '_' . $postfix );
    }

    protected static function config_constants_to_array() {
        $out = array();
        foreach ( array( 'locked', 'path', 'ssh_db_path', 'url', 'db_host', 'db_user', 'db_port', 'db_name', 'db_password', 'ssh_db_host', 'ssh_db_user', 'ssh_db_path', 'ssh_host', 'ssh_user', 'ssh_port', 'excludes' ) as $postfix ) {
            $out[ $postfix ] = defined( self::config_constant( $postfix ) ) ? constant( self::config_constant( $postfix ) ) : null;
        }

        return $out;
    }

    private static function _trim_url( $url ) {
        $url = trim( $url, '/' );
        if ( ! preg_match( '#^http(s)?://#', $url ) ) {
            $url = 'http://' . $url;
        }
        $url_parts = parse_url( $url );
        $domain    = preg_replace( '/^www\./', '', $url_parts['host'] );

        return $domain;
    }
}
