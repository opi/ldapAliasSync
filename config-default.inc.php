<?php
// Default configuration settings for ldapAliasSync
// Copy this file in config.inc.php, and override the values you need.

$rcmail_config['ldapAliasSync'] = array(
    // Mail parameters
    'mail' => array(
        'remove_domain'     => true,            // remove domain part from login name (xyz@example.com --> xyz)
        'search_domain'     => 'example.com',   // add domain to login (xyz --> xyz@example.com) (optional)
        'find_domain'       => 'example.com',   // add domain to found local parts (xyz --> xyz@example.com) (optional)
        'dovecot_seperator' => '*',             // remove admin name (dovecot master user) at login (optional)
    ),

    // LDAP parameters
    'ldap' => array(
        'server'     => 'ldap://localhost',                                         // LDAP server address (required)
        'bind_dn'    => 'cn=mail,dc=example,dc=com',                                // LDAP Bind DN (optional)
        'bind_pw'    => 'secret',                                                   // Bind password (optional)
        'base_dn'    => 'ou=aliases,dc=example,dc=com',                             // LDAP search base (required)
        'filter'     => '(aliasedobjectname=uid=%s,ou=users,dc=example,dc=org)',    // LDAP search filter (required)
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
