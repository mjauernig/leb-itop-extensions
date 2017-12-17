<?php
// config

// DNS or IP of mysql server
$dbhost = "localhost";
// Database user with whom the connection is established
$dbuser = "itop";
// Password of the database user
$dbpass = "123456";
// Name of the iTop database
$dbname = "itop";
// Name of the synchronization database table
$dbsynytable = "synchro_data_contactsfromldap";

// Connection String of the domain controller
$ldapserver = "ldaps://dc.example.local";
// ldap user, with whom the connection is established
$ldapuser   = "adreader";
// Password of the ldap user
$ldappass   = "123456";
// The base distinguished name for the ldap queries
$ldapdn     = "DC=example,DC=local";
// The ldap group, whose users are to be synchronized
$ldapgroup  = "CN=Groupname,OU=SubOU,OU=GroupOU,DC=example,DC=local";


// script


// Connect to mysql server
$mysqli = new mysqli($dbhost, $dbuser, $dbpass, $dbname);
if($mysqli->connect_errno)
{
        exit("MYSQL connect failed: " . $mysqli->connect_error . "\n");
}


// Get the created organizations from iTop and store them in an array
$orgs   = array();
if($result = $mysqli->query("SELECT * FROM organization"))
{
        while($row=$result->fetch_assoc())
        {
                $orgs[$row["name"]] = $row["id"];
        }
        $result->close();
}
if(count($orgs)==0)
{
        exit("No organizations found in iTop database");
}


// Definition of the fields, which will be synchronized
$fields = array("objectGUID", "sAMAccountName", "sn", "givenName", "telephoneNumber", "mail", "company", "memberOf");

// Connect to ldap server
$link   = ldap_connect($ldapserver) or exit("LDAP connect failed.\n");
ldap_set_option($link, LDAP_OPT_PROTOCOL_VERSION, 3);
$bind   = ldap_bind($link, $ldapuser, $ldappass);

// Search for all user (category person) in ldap
$result = ldap_search($link, $ldapdn, "(&(objectClass=user)(objectCategory=person))", $fields) or exit("LDAP error in search query: " . ldap_error($link));
$entries= ldap_get_entries($link, $result);

// For each user ...
for($i=0;$i<$entries["count"];$i++)
{
        $e = $entries[$i];
        $username  = GetValue("samaccountname", $e);

        // ... check, if user has no group membership(s)
        if(!array_key_exists("memberof", $e))
        {
                // if user has no group membership(s), skip user
                echo "SKIP USER: " . $username . " no group membership\n";
                continue;
        }
        // ... check, if user is not member of required ldap group
        if(!in_array($ldapgroup, $e["memberof"]))
        {
                // if user is not member of required ldap group, skip user
                echo "SKIP USER: " . $username . " no ldap membership\n";
                continue;
        }

        // ... get values of user
        $guid      = bin2hex(GetValue("objectguid", $e));
        $name      = GetValue("sn", $e);
        $firstname = GetValue("givenname", $e);
        $telephone = GetValue("telephonenumber", $e);
        $email     = GetValue("mail", $e);
        $company   = GetValue("company", $e);
        $orgId     = GetOrgId($company, $orgs);

        // ... check, if one of iTop reuired fields is empty
        if(strlen($name)==0 || strlen($firstname)==0 || $orgId==0)
        {
                // if one of iTop required fields is empty, skip user
                echo "SKIP USER: " . $username . " not all required data " . strlen($name) . ":" . strlen($firstname) . ":" . $orgId . "\n";
                continue;
        }

        // ... check, if user is already in synchronisation database
        //      The check is done by, if user-guid exist as primary_key
        $query = "SELECT primary_key FROM " . $dbsynytable . " WHERE primary_key='" . $mysqli->real_escape_string($guid) . "'";
        $result = $mysqli->query($query);
        $newEntry = $result->num_rows==0 ? true : false;

        // ... build insert/update query
        $query = "";
        if($newEntry)
        {
                $query = "INSERT INTO " . $dbsynytable . " SET primary_key='" . $mysqli->real_escape_string($guid) . "', status='active', notify='no'";
        }
        else
        {
                $query = "UPDATE " . $dbsynytable . " SET primary_key='" . $mysqli->real_escape_string($guid) . "'";
        }

        $query.= ", name='" . $mysqli->real_escape_string($name) . "', first_name='" . $mysqli->real_escape_string($firstname) . "'";

        if($telephone)
        {
                $query.= ", phone='" . $mysqli->real_escape_string($telephone) . "'";
        }
        if($email)
        {
                $query.= ", email='" . $mysqli->real_escape_string($email) . "'";
        }
        if($orgId>0)
        {
                $query.= ", org_id=".$orgId;
        }

        if(!$newEntry)
        {
                $query.= " WHERE primary_key='" . $mysqli->real_escape_string($guid) . "'";
        }

        // ... insert/update ldap user into iTop synchronization table
        $mysqli->query($query);
        echo $query."\n";
}

$mysqli->close();
ldap_close($link);

// Function to get the organization id by the given name
function GetOrgId($name, $orgs)
{
        if(array_key_exists($name, $orgs))
        {
                return $orgs[$name];
        }

        return 0;
}

// Function to get the value from a ldap resource by the given attribute name
function GetValue($name, $e)
{
        if(array_key_exists($name, $e))
        {
                return $e[$name][0];
        }

        return null;
}
