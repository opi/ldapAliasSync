<?php
/*
 * Default configuration settings for ldapAliasSync roundcube plugin
 * Copy this file in config.inc.php, and override the values you need.
*/

$rcmail_config['ldapAliasSync'] = array(
    // Mail parameters
    'mail' => array(
        # Remove domain part from login name (xyz@example.com --> xyz) if given
        # Set to true, if you intend to only lookup the local part in LDAP;
        # set to false, if you intend to lookup the whole email address.
        'remove_domain'     => true,
        
        # Domain to add to login (xyz --> xyz@example.com) if none is given (optional)
        # This parameter is needed, if users login with only their local parts (xyz),
        # but you intend to query the LDAP for the whole email address
        'search_domain'     => 'example.com',
        
        # Domain to add to found local parts (asdf --> asdf@example.com) (optional)
        # If the returned value does only contain the local part of an email address,
        # this domain will be used as the domain part.
        # This may only be empty, if all identities to be found contain a domain part
        # in their email addresses as all identities without a domain part in the email
        # address will not be returned!
        'find_domain'       => 'example.com',
        
        # Dovecot master user seperator (optional)
        # If you use the dovecot impersonation feature, this seperator will be used
        # in order to determine the actual login name.
        # Set it to the same character if using this feature, otherwise you can also
        # leave it empty.
        'dovecot_seperator' => '*',
    ),

    // LDAP parameters
    'ldap' => array(
        # LDAP server address (required)
        'server'     => 'ldap://localhost',
        
        # LDAP Bind DN (optional)
        'bind_dn'    => 'cn=mail,dc=example,dc=com',
        
        # Bind password (optional)
        'bind_pw'    => 'secret',
        
        # LDAP search base (required)
        'base_dn'    => 'ou=aliases,dc=example,dc=com',
        
        # LDAP search filter (required)
        # This open filter possibility is the heart of the LDAP search.
        # Use '%1$s' as a place holder for the login name (domain part depending on the configuration above)
        # However, remember to search for the original entry, too (e.g. 'uid=%1$s'), as this is an identity as well!
        'filter'     => '(|(uid=%1$s)(aliasedobjectname=uid=%1$s,ou=users,dc=example,dc=org)',
        
        # LDAP email attribute (required)
        # If only the local part is returned, the 'find_domain' is appended (e.g. uid=asdf --> asdf@example.com).
        # If no domain part is returned and no 'find_domain' is given, the identity will not be fetched!
        'attr_mail'  => 'uid',
        
        # LDAP name attribute (optional)
        'attr_name'  => 'cn',
        
        # LDAP organization attribute (optional)
        'attr_org'   => 'o',
        
        # LDAP reply-to attribute (optional)
        'attr_reply' => '',
        
        # LDAP bcc (blind carbon copy) attribute (optional)
        'attr_bcc'   => '',
        
        # LDAP signature attribute (optional)
        'attr_sig'   => '',
    ),
);
?>
