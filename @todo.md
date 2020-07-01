# 1.
**Verify the menu pages**
    Check base controller PluginFactory::isPluginMenuPage()

# 2.
**Search and replace**
    pattern: @(return|param)([ ]+)\((.*)\)
    replace: @$1$2$3

# 3.
**Add the alert handler to the PHPError class**

# 4. 
**check whether the internal ip address can be filtered**
    ip-factory.php

# 5.
-- Install the plugin global settings
-- installer#L87

# 6.
-- Hook into the 'add_user_role', 'update_user_role', 'remove_user_role' action hooks, and look whether the 'is_updating_user_cap' is set to true, then re-update the 'capability' title field to 'role'.