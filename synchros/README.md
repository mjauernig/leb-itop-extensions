# ldap2synchro.php

This script imports the user of an *ldap / active directory* group into the person table of iTop.

## Warning

Be carefull, in existing installations! The sync-process creates for every user a new person-object. If you have created manual person's you may have doublettes.

## How the script works

1. The script searches for all user (category person) in the ldap directory.
1. Then the script do two checks
   1. Is the user in the ldap-group which should be synchronized
   1. Are the reuired fields of itop (firstname, surname, company) are given.
1. Are both checks valid, the script insert or update the person object into the synchronization table.
1. Then you have to trigger the itop build-in syncho-script to execute the indeed synchronization.

**Important**
The content (company name) of the ldap field *company* must have exactly the spelling of the organization names in itop (Data Management > Organization).

## Requirements

1. iTop with MySQL database
2. PHP with active mysqlnd extension

## Step 1 - Create iTop Data Synchronization Reference

Source: https://wiki.openitop.org/doku.php?id=2_4_0:advancedtopics:data_synchronization

Under *iTop > Admin Tools > Synchronization Data Sources* create a new datasource. Important are the fields
1. Data table = This is the name of the synchronization table, which will be created in the database. Example name: **synchro_data_contactsfromldap**
1. Reconciliation policy = Set this to **primary_key**.

After saving, search in the url for the synchrodatasource id. Remember this id. Example: https://itop.example.local/pages/UI.php?operation=details&class=SynchroDataSource&id= **2** &c[menu]=DataSources


## Step 2 - ldap2synchro.php & crontab

1. Save the ldap2synchro.php script. I preffer the synchro folder of the iTop installation path. Example **/var/www/itop.example.local/synchro/ldap2synchro.php**
1. Edit the ldap2synchro.php script. Change the config variables at the beginning.
   * Set $dbsynytable to the synchronization table name from step 1.
1. Edit the crontab with the command *sudo crontab -e*. Execute the script one minute after every full hour.
```
# m h  dom mon dow   command
1 * * * * /usr/bin/php /var/www/itop.example.local/synchro/ldap2synchro.php
```
1. Create the file /etc/itop/cron.params to store the admin credentials. Maybee you create separate credentials for the script authentication against itop.
```
auth_user=admin
auth_pwd=yourpassword
```
1. Edit the crontab with the command *sudo crontab -e* again. Trigger the synchro_exec.php for the indeed synchronization.  Execute the script five minutes after every full hour.
   * Set --data_sources=x to the synchrodatasource id from step 1.
```
# m h  dom mon dow   command
5 * * * * /usr/bin/php /var/www/itop.example.local/synchro/synchro_exec.php --param_file=/etc/itop/cron.params --data_sources=2
```
