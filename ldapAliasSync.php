<?php
/*
 * LDAP Alias Sync: Syncronize users' identities (name, email, organization) by querying an LDAP server.
 * Based on the 'Identiteam' Plugin by André Rodier <andre.rodier@gmail.com>
 * Author: Lukas Mika <lukas.mika@web.de>
 * Licence: GPLv3. (See copying)
 */
class ldapAliasSync extends rcube_plugin
{
    public $task = 'login';

    private $config;
    private $app;

    // mail parameters
    private $mail;

    // LDAP parameters
    private $ldap;
    private $server;
    private $filter;
    private $domain;
    private $fields;
    private $conn;

    // Internal flags
    private $initialised;

    function init()
    {
        try
        {
            write_log('ldapAliasSync', 'Initialising');
            
            # Load default config, and merge with users' settings
            $this->load_config('config-default.inc.php');
            $this->load_config('config.inc.php');

            $this->app = rcmail::get_instance();
            $this->config = $this->app->config->get('ldapAliasSync');

            # Load LDAP & mail config at once
            $this->ldap = $this->config['ldap'];
            $this->mail = $this->config['mail'];

            $this->server = $this->ldap['server'];
            $this->filter = $this->ldap['filter'];
            $this->domain = $this->ldap['domain'];
            $this->bind_dn = $this->ldap['bind_dn'];
            $this->bind_pw = $this->ldap['bind_pw'];
            $this->fields  = $this->ldap['fields'];

            $this->conn = ldap_connect($this->server);

            if ( is_resource($this->conn) )
            {
                ldap_set_option($this->conn, LDAP_OPT_PROTOCOL_VERSION, 3);

                $bound = ldap_bind($this->conn, $this->bind_dn, $this->bind_pw);

                if ( $bound )
                {
                    // Create signature
                    $this->add_hook('user2email', array($this, 'user2email'));
                    $this->initialised = true;
                }
                else
                {
                    $log = sprintf("Bind to server '%s' failed. Con: (%s), Error: (%s)",
                        $this->server,
                        $this->conn,
                        ldap_errno($this->conn));
                    write_log('ldapAliasSync', $log);
                }
            }
            else
            {
                $log = sprintf("Connection to the server failed: (Error=%s)", ldap_errno($this->conn));
                write_log('ldapAliasSync', $log);
            }
        }
        catch ( Exception $exc )
        {
            write_log('ldapAliasSync', 'Fail to initialise: '.$exc->getMessage());
        }

        if ( $this->initialised )
            write_log('ldapAliasSync', 'Initialised');
    }

    /**
     * user2email
     * 
     * See http://trac.roundcube.net/wiki/Plugin_Hooks
     * Return values:
     * email: E-mail address (or array of arrays with keys: email, name, organization, reply-to, bcc, signature, html_signature) 
     */
    function user2email($args)
    {
        $login = $args['user'];          # User login
        $first = $args['first'];         # True if one entry is expected
        $extended = $args['extended'];   # True if array result (email and identity data) is expected
        $email = $args['email'];

        # ensure we return valid information
        $args['extended'] = true;
        $args['first'] = false;
        $args['abort'] = false;

        try
        {
            # load ldap & mail confg
            $ldap = $this->ldap;
            $mail = $this->mail;

            # if set to true, the domain name is removed before the lookup 
            $filter_remove_domain = $this->config['ldap']['filter_remove_domain'];

            if ( $filter_remove_domain )
            {            
                $login = array_shift(explode('@', $login));
            }
            else
            {
                # check if we need to add a domain if not specified in the login name
                if ( !strstr($login, '@') && $mail['domain'] )
                {
                    $domain = $mail['domain'];
                    $login = "$login@$domain" ;
                }
            }

            # Check if dovecot master user is used. Use the same configuration name than
            # dovecot_impersonate plugin for roundcube
            $separator = $this->config['mail']['dovecot_impersonate_seperator'];

            if ( strpos($login,$separator) !== false )
            {   
                $log = sprintf("Removed dovecot impersonate separator (%s) in the login name", $separator);
                write_log('ldapAliasSync', $log);

                $login = array_shift(explode($separator, $login));
            }   

            $filter = sprintf($ldap['filter'], $login);
            $result = ldap_search($this->conn, $this->domain, $this->filter, $this->fields);

            if ( $result )
            {
                $info = ldap_get_entries($this->conn, $result);

                if ( $info['count'] >= 1 )
                {
                    $log = sprintf("Found the user '%s' in the database", $login);
                    write_log('ldapAliasSync', $log);

                    $identities = array();

                    foreach ( $result as $ldapID )
                    {
                        $name = $ldapID['cn'];
                        $email = $ldapID['uid'].'@'.$domain;
                        $organisation = $ldapID['o'];

                        if ( !$organisation ) $organisation = '';
                        if ( !$name ) $name = '';

                        $identity[] = array(
                            'email' => $email,
                            'name' => $name,
                            'organization' => $organisation,
                        );
                            
                        array_push($identities[], $identity);
                    }

                    $args['email'] = $identities;
                    
                    if (count($identities[]) > 0 && $db_identities[] = $this->app->user->list_identities())
                    {
                        foreach ($db_identities as $db_identity)
                        {
                            $in_ldap = null;
                            foreach ($identities as $identity)
                            {
                                if($db_identity['email'] == $identity['email'] && !$in_ldap)
                                {
                                    $in_ldap = $db_identity['identity_id'];
                                }
                            }
                            if (!$in_ldap)
                            {
                                $db_user->delete_identity($in_ldap);
                            }
                        }
                        $log = sprintf("Identities synced for %s", $login);
                        write_log('ldapAliasSync', $log);
                    }
                }
                else
                {
                    $log = sprintf("User '%s' not found (pass 2). Filter: %s", $login, $filter);
                    write_log('ldapAliasSync', $log);
                }
            }
            else
            {
                $log = sprintf("User '%s' not found (pass 1). Filter: %s", $login, $filter);
                write_log('ldapAliasSync', $log);
            }

            ldap_close($this->conn);
        }
        return $args;
    }

}
?>
