<?php
// Default configuration settings for ldapAliasSync
// Copy this file in config.inc.php, and override the values you need.

$rcmail_config['ldapAliasSync'] = array(
    // Mail parameters
    'mail' => array(
        'domain' => 'example.com',                          // If necessary, you can specify a domain to add after the login name
        'dovecot_impersonate_seperator' => '*'              // If using dovecot master users, remove the admin name before the lookup
        'remove_domain' => true,                            // if set to true, the domain name (eg. xxxx@example.com) is removed before the lookup
    ),

    // LDAP parameters
    'ldap' => array(
        'server'     => 'ldap://localhost',                                         // Your LDAP server address (required)
        'bind_dn'    => 'cn=mail,dc=example,dc=com',                                // LDAP Bind DN (optional)
        'bind_pw'    => 'secret',                                                   // Bind password (optional)
        'base_dn'    => 'ou=aliases,dc=example,dc=com',                             // LDAP search base (required)
        'filter'     => '(aliasedobjectname=uid=%s,ou=users,dc=example,dc=org)',    // The LDAP filter to use
        'attr_mail'  => 'uid',                                                      // LDAP email attribute (required)
                                                                                    //  --> can also be local part
        'attr_name'  => 'cn',                                                       // LDAP name attribute (optional)
        'attr_org'   => 'o',                                                        // LDAP organization attribute (optional)
        'attr_reply' => '',                                                         // LDAP reply-to attribute (optional)
        'attr_bcc'   => '',                                                         // LDAP bcc attribute (optional)
        'attr_sig'   => '',                                                         // LDAP signature attribute (optional)
    ),
);
?>
