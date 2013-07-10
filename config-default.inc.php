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
        'server'     => 'localhost',                      // Your LDAP server address
        'bind_dn'    => 'cn=mail,dc=example,dc=com',      // The account to bind to the LDAP server
        'bind_pw'    => 'secret',                         // Bind password
        'base_dn'    => 'ou=accounts,dc=example,dc=com',  // LDAP search base
        'filter'     => '(uid=%s)',                       // The LDAP filter to use
        'attr_mail'  => 'uid',                            // LDAP email attribute
        'attr_name'  => 'cn',                             // LDAP name attribute
        'attr_org'   => 'o',                              // LDAP organization attribute
        'attr_reply' => '',                               // LDAP reply-to attribute
        'attr_bcc'   => '',                               // LDAP bcc attribute
        'attr_sig'   => '',                               // LDAP signature attribute
    ),
);
?>
