<?php
/*
 * LDAP Alias Sync: Syncronize users' identities (name, email, organization, reply-to, bcc, signature)
 * by querying an LDAP server's aliasses.
 *
 * Based on the 'IdentiTeam' Plugin by André Rodier <andre.rodier@gmail.com>
 * Author: Lukas Mika <lukas.mika@web.de>
 * Licence: GPLv3. (See copying)
 */
class ldapAliasSync extends rcube_plugin {
    
    public $task = 'login';

    // Internal variables
    private $initialised;
    private $config;
    private $app;
    private $conn;

    // mail parameters
    private $mail;
    private $search_domain;
    private $replace_domain;
    private $find_domain;
    private $separator;

    // LDAP parameters
    private $ldap;
    private $server;
    private $bind_dn;
    private $bind_pw;
    private $base_dn;
    private $filter;
    private $attr_mail;
    private $attr_name;
    private $attr_org;
    private $attr_reply;
    private $attr_bcc;
    private $attr_sig;
    private $fields;

    function init() {
        try {
            write_log('ldapAliasSync', 'Initialising');
            
            # Load default config, and merge with users' settings
            $this->load_config('config-default.inc.php');
            $this->load_config('config.inc.php');

            $this->app = rcmail::get_instance();
            $this->config = $this->app->config->get('ldapAliasSync');

            # Load LDAP & mail config at once
            $this->ldap = $this->config['ldap'];
            $this->mail = $this->config['mail'];

            # Load LDAP configs
            $this->server       = $this->ldap['server'];
            $this->bind_dn      = $this->ldap['bind_dn'];
            $this->bind_pw      = $this->ldap['bind_pw'];
            $this->base_dn      = $this->ldap['base_dn'];
            $this->filter       = $this->ldap['filter'];
            $this->attr_mail    = $this->ldap['attr_mail'];
            $this->attr_name    = $this->ldap['attr_name'];
            $this->attr_org     = $this->ldap['attr_org'];
            $this->attr_reply   = $this->ldap['attr_reply'];
            $this->attr_bcc     = $this->ldap['attr_bcc'];
            $this->attr_sig     = $this->ldap['attr_sig'];
            
            $this->fields = array($this->attr_mail, $this->attr_name, $this->attr_org, $this->attr_reply,
                $this->attr_bcc, $this->attr_sig);

            # Load mail configs
            $this->search_domain  = $this->mail['search_domain'];
            $this->replace_domain = $this->mail['replace_domain'];
            $this->find_domain    = $this->mail['find_domain'];
            $this->separator      = $this->mail['dovecot_seperator'];

            # LDAP Connection
            $this->conn = ldap_connect($this->server);

            if ( is_resource($this->conn) ) {
                ldap_set_option($this->conn, LDAP_OPT_PROTOCOL_VERSION, 3);

                # Bind to LDAP (with account or anonymously)
                if ( $this->bind_dn ){
                    $bound = ldap_bind($this->conn, $this->bind_dn, $this->bind_pw);
                } else {
                    $bound = ldap_bind($this->conn);
                }
                
                if ( $bound ) {
                    # register hook
                    $this->add_hook('user2email', array($this, 'user2email'));
                    $this->initialised = true;
                } else {
                    $log = sprintf("Bind to server '%s' failed. Con: (%s), Error: (%s)",
                        $this->server,
                        $this->conn,
                        ldap_errno($this->conn));
                    write_log('ldapAliasSync', $log);
                }
            } else {
                $log = sprintf("Connection to the server failed: (Error=%s)", ldap_errno($this->conn));
                write_log('ldapAliasSync', $log);
            }
        } catch ( Exception $exc ) {
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
    function user2email($args) {
        $login    = $args['user'];      # User login
        $first    = $args['first'];     # True if one entry is expected
        $extended = $args['extended'];  # True if array result (email and identity data) is expected
        $email    = $args['email'];

        # ensure we return valid information
        $args['extended'] = true;
        $args['first']    = false;
        $args['abort']    = false;

        try {
            # Get the local part and the domain part of login
            if ( strstr($login, '@') ) {
                $login_parts = explode('@', $login);
                $local_part  = array_shift($login_parts);
                $domain_part = array_shift($login_parts);

                if ( $this->replace_domain && $this->search_domain ) {
                    $domain_part = $this->search_domain;
                }
            } else {
                $local_part = $login;
                if ( $this->search_domain ) {
                    $domain_part = $this->search_domain;
                }
            }
            
            # Check if dovecot master user is used.
            if ( strstr($login, $this->separator) ) {   
                $log = sprintf("Removed dovecot impersonate separator (%s) in the login name", $this->separator);
                write_log('ldapAliasSync', $log);

                $local_part = array_shift(explode($this->separator, $local_part));
            }   

            # Set the search email address
            if ( $domain_part ) {
                $login_email = "$local_part@$domain_part";
            } else {
                $domain_part = '';
                $login_email = '';
            }

            # Replace place holders in the LDAP filter with login data
            $ldap_filter = sprintf($this->filter, $login, $local_part, $domain_part, $login_email);
            
            # Search for LDAP data
            $result = ldap_search($this->conn, $this->domain, $ldap_filter, $this->fields);

            if ( $result ) {
                $info = ldap_get_entries($this->conn, $result);

                if ( $info['count'] >= 1 ) {
                    $log = sprintf("Found the user '%s' in the database", $login);
                    write_log('ldapAliasSync', $log);

                    $identities = array();

                    # Collect the identity information
                    foreach ( $result as $ldapID ) {
                        $email = $ldapID[$attr_mail];
                        
                        if ( $attr_name )  $name         = $ldapID[$attr_name];
                        if ( $attr_org )   $organisation = $ldapID[$attr_org];
                        if ( $attr_reply ) $reply        = $ldapID[$attr_reply];
                        if ( $attr_bcc )   $bcc          = $ldapID[$attr_bcc];
                        if ( $attr_sig )   $signature    = $ldapID[$attr_sig];

                        # If we only found the local part and have a find domain, append it
                        if ( $email && !strstr($email, '@') && $find_domain ) $email = "$email@$find_domain";

                        # Only collect the identities with valid email addresses
                        if ( strstr($email, '@') ) {
                            if ( !$name )         $name         = '';
                            if ( !$organisation ) $organisation = '';
                            if ( !$reply )        $reply        = '';
                            if ( !$bcc )          $bcc          = '';
                            if ( !$signature )    $signature    = '';

                            # If the signature starts with an HTML tag, we mark the signature as HTML
                            if ( preg_match('/^\s*<[a-zA-Z]+/', $signature) ) {
                                $isHtml = 1;
                            } else {
                                $isHtml = 0;
                            }
    
                            $identity[] = array(
                                'email'          => $email,
                                'name'           => $name,
                                'organization'   => $organisation,
                                'reply-to'       => $reply,
                                'bcc'            => $bcc,
                                'signature'      => $signature,
                                'html_signature' => $isHtml,
                            );
                                
                            array_push($identities[], $identity);
                        } else {
                            $log = sprintf("Domain missing in email address '%s'", $email);
                            write_log('ldapAliasSync', $log);
                        }
                    }
                    
                    # Return structure for our LDAP identities
                    $args['email'] = $identities;
                    
                    # Check which identities are available in database but nut in LDAP and delete those
                    if ( count($identities) > 0 && $db_identities[] = $this->app->user->list_identities() ) {
                        foreach ( $db_identities as $db_identity ) {
                            $in_ldap = null;
                            
                            foreach ( $identities as $identity ) {
                                # email is our only comparison parameter
                                if( $db_identity['email'] == $identity['email'] && !$in_ldap ) {
                                    $in_ldap = $db_identity['identity_id'];
                                }
                            }
                            
                            # If this identity does not exist in LDAP, delete it from database
                            if ( !$in_ldap ) {
                                $db_user->delete_identity($in_ldap);
                            }
                        }
                        $log = sprintf("Identities synced for %s", $login);
                        write_log('ldapAliasSync', $log);
                    }
                } else {
                    $log = sprintf("User '%s' not found (pass 2). Filter: %s", $login, $filter);
                    write_log('ldapAliasSync', $log);
                }
            } else {
                $log = sprintf("User '%s' not found (pass 1). Filter: %s", $login, $filter);
                write_log('ldapAliasSync', $log);
            }

            ldap_close($this->conn);
        } catch(Exception $e) {
            echo 'Caught exception: ',  $e->getMessage(), "\n";
	}
        return $args;
    }
}
?>
